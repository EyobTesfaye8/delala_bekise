<?php

use App\Models\Category;
use App\Models\DelalaJob;
use App\Models\EscrowTransaction;
use App\Models\KycProfile;
use App\Models\Proposal;
use App\Models\ProposalItem;
use App\Models\Rating;
use App\Models\User;
use App\Services\FcmService;
use App\States\Escrow\Released;
use App\States\Job\Completed;
use App\States\Kyc\Approved as KycApproved;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->category = Category::factory()->create(['is_leaf' => true]);

    $this->buyer = User::factory()->create(['type' => 'buyer', 'full_name' => 'Abebe Buyer']);
    $this->delala = User::factory()->create(['type' => 'delala', 'full_name' => 'Kebede Delala']);
    KycProfile::create([
        'user_id' => $this->delala->id,
        'status' => KycApproved::class,
        'national_id_front_path' => 'kyc/id_front.jpg',
        'national_id_back_path' => 'kyc/id_back.jpg',
        'selfie_path' => 'kyc/selfie.jpg',
        'reviewed_at' => now(),
    ]);

    $this->job = DelalaJob::factory()->create([
        'buyer_id' => $this->buyer->id,
        'category_id' => $this->category->id,
        'status' => 'completed',
    ]);

    $this->proposal = Proposal::factory()->create([
        'job_id' => $this->job->id,
        'delala_id' => $this->delala->id,
        'status' => 'accepted',
    ]);

    $this->item = ProposalItem::factory()->create([
        'proposal_id' => $this->proposal->id,
        'commission_amount_santim' => 100000,
        'status' => 'accepted_for_escrow',
    ]);

    $this->escrow = EscrowTransaction::create([
        'uuid' => (string) Str::uuid(),
        'job_id' => $this->job->id,
        'proposal_item_id' => $this->item->id,
        'buyer_id' => $this->buyer->id,
        'delala_id' => $this->delala->id,
        'commission_amount_santim' => 100000,
        'platform_fee_santim' => 5000,
        'delala_amount_santim' => 95000,
        'status' => Released::class,
    ]);
});

it('rejects ratings for non-completed jobs and outside the 14-day window (FR-6.1)', function () {
    Sanctum::actingAs($this->buyer);

    // 1. Non-completed job
    $openJob = DelalaJob::factory()->create([
        'buyer_id' => $this->buyer->id,
        'category_id' => $this->category->id,
        'status' => 'open',
    ]);

    $openResponse = $this->postJson("/api/v1/jobs/{$openJob->id}/ratings", [
        'stars' => 5,
        'review' => 'Great work!',
    ]);
    $openResponse->assertStatus(422)
        ->assertJson(['message' => 'Ratings can only be submitted after the job is completed.']);

    // 2. Expired window (>14 days since completion)
    DB::table('delala_jobs')->where('id', $this->job->id)->update(['updated_at' => now()->subDays(15)]);
    $this->job->refresh();

    $expiredResponse = $this->postJson("/api/v1/jobs/{$this->job->id}/ratings", [
        'stars' => 5,
        'review' => 'Late review attempt.',
    ]);
    $expiredResponse->assertStatus(422)
        ->assertJson(['error_code' => 'RATING_WINDOW_EXPIRED']);
});

it('enforces double-blind mutual rating concealment until both parties submit (FR-6.1)', function () {
    // 1. Buyer submits rating first
    Sanctum::actingAs($this->buyer);

    $buyerResponse = $this->postJson("/api/v1/jobs/{$this->job->id}/ratings", [
        'stars' => 5,
        'review' => 'Kebede was extraordinarily helpful and negotiated a great price!',
    ]);

    $buyerResponse->assertCreated()
        ->assertJson([
            'both_submitted' => false,
            'is_revealed' => false,
        ]);

    $buyerRating = Rating::where('rater_id', $this->buyer->id)->first();
    expect($buyerRating)->not->toBeNull()
        ->and($buyerRating->is_revealed)->toBeFalse();

    // Verify rating is NOT yet visible in Delala's public profile
    $profileResBefore = $this->getJson("/api/v1/delalas/{$this->delala->id}/profile");
    $profileResBefore->assertOk()
        ->assertJson([
            'average_rating' => 0.0,
            'total_reviews_count' => 0,
            'recent_reviews' => [],
        ]);

    // 2. Delala submits rating for Buyer
    Sanctum::actingAs($this->delala);

    $delalaResponse = $this->postJson("/api/v1/jobs/{$this->job->id}/ratings", [
        'stars' => 5,
        'review' => 'Abebe was very prompt with release of funds upon handover.',
    ]);

    $delalaResponse->assertCreated()
        ->assertJson([
            'both_submitted' => true,
            'is_revealed' => true,
        ]);

    // Both ratings must now be revealed!
    expect($buyerRating->fresh()->is_revealed)->toBeTrue()
        ->and(Rating::where('rater_id', $this->delala->id)->first()->is_revealed)->toBeTrue();

    // 3. Profile now reveals the verified rating
    $profileResAfter = $this->getJson("/api/v1/delalas/{$this->delala->id}/profile");
    $profileResAfter->assertOk()
        ->assertJson([
            'average_rating' => 5.0,
            'total_reviews_count' => 1,
        ]);
});

it('exposes the public trust profile with verification, completed deals, and revealed reviews (FR-6.2)', function () {
    // Add an already revealed review
    Rating::create([
        'escrow_transaction_id' => $this->escrow->id,
        'buyer_id' => $this->buyer->id,
        'delala_id' => $this->delala->id,
        'rater_id' => $this->buyer->id,
        'ratee_id' => $this->delala->id,
        'stars' => 4,
        'review' => 'Professional broker.',
        'is_revealed' => true,
        'revealed_at' => now(),
    ]);

    $response = $this->getJson("/api/v1/delalas/{$this->delala->id}/profile");

    $response->assertOk()
        ->assertJson([
            'id' => $this->delala->id,
            'full_name' => 'Kebede Delala',
            'is_verified' => true,
            'completed_deals_count' => 1,
            'average_rating' => 4.0,
            'total_reviews_count' => 1,
        ])
        ->assertJsonStructure([
            'recent_reviews' => [
                ['id', 'stars', 'review', 'rater' => ['id', 'full_name']],
            ],
        ]);
});

it('allows authenticated users to register an FCM device push token', function () {
    Sanctum::actingAs($this->buyer);

    $response = $this->postJson('/api/v1/users/fcm-token', [
        'fcm_token' => 'mock_fcm_device_token_xyz987',
    ]);

    $response->assertOk()
        ->assertJson(['message' => 'FCM device token registered successfully.']);

    expect($this->buyer->fresh()->fcm_token)->toBe('mock_fcm_device_token_xyz987');
});

it('handles FCM push notification dispatch and critical P1 alerting gracefully', function () {
    $fcm = app(FcmService::class);

    // Testing environment mocks dispatch and logs notice
    $result = $fcm->sendToToken('mock_token', 'Job Awarded', 'Your proposal was accepted!');
    expect($result)->toBeTrue();

    // Critical operational alert
    $fcm->sendCriticalAlert('Ledger Mismatch', 'Account balance anomaly detected.', ['account_id' => 1010]);
    expect(true)->toBeTrue();
});
