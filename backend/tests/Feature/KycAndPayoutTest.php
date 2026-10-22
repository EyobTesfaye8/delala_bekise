<?php

use App\Models\Category;
use App\Models\DelalaJob;
use App\Models\KycProfile;
use App\Models\PayoutDestination;
use App\Models\User;
use App\Notifications\KycApproved;
use App\Notifications\KycRejected;
use App\States\Kyc\Approved;
use App\States\Kyc\UnderReview;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->delala = User::factory()->create(['type' => 'delala']);
    $this->admin = User::factory()->create(['type' => 'admin']);
    $this->buyer = User::factory()->create(['type' => 'buyer']);
});

it('FR-2.1 & FR-2.3: allows Delala to submit KYC with S3 keys and sets status to under_review', function () {
    $response = $this->actingAs($this->delala)->postJson('/api/v1/kyc', [
        'document_type' => 'kebele_id',
        'id_document_s3_key' => 'mock/kyc/id_front.jpg',
        'id_document_back_s3_key' => 'mock/kyc/id_back.jpg',
        'selfie_s3_key' => 'mock/kyc/selfie.jpg',
    ]);

    $response->assertStatus(201)
        ->assertJson(['message' => 'KYC documents submitted successfully. Please wait for admin approval.']);

    $this->assertDatabaseHas('kyc_profiles', [
        'user_id' => $this->delala->id,
        'document_type' => 'kebele_id',
        'status' => 'under_review',
    ]);
});

it('FR-2.1: rejects non-Delala trying to submit KYC', function () {
    $response = $this->actingAs($this->buyer)->postJson('/api/v1/kyc', [
        'document_type' => 'national_id',
        'id_document_s3_key' => 'mock/kyc/id_front.jpg',
        'selfie_s3_key' => 'mock/kyc/selfie.jpg',
    ]);

    $response->assertStatus(403)
        ->assertJson(['message' => 'Only Delalas can submit KYC.']);
});

it('FR-2.1: allows Delala to check their KYC status and document URLs', function () {
    $kyc = KycProfile::create([
        'user_id' => $this->delala->id,
        'document_type' => 'passport',
        'id_document_s3_key' => 'mock/kyc/passport.jpg',
        'selfie_s3_key' => 'mock/kyc/selfie.jpg',
    ]);
    $kyc->status->transitionTo(UnderReview::class);

    $response = $this->actingAs($this->delala)->getJson('/api/v1/kyc');

    $response->assertStatus(200)
        ->assertJsonPath('status', 'under_review')
        ->assertJsonStructure(['status', 'profile', 'urls']);
});

it('AR-1: admin can view KYC queue, approve submission, and notify Delala', function () {
    Notification::fake();

    $kyc = KycProfile::create([
        'user_id' => $this->delala->id,
        'document_type' => 'national_id',
        'id_document_s3_key' => 'mock/kyc/id_front.jpg',
        'id_document_back_s3_key' => 'mock/kyc/id_back.jpg',
        'selfie_s3_key' => 'mock/kyc/selfie.jpg',
    ]);
    $kyc->status->transitionTo(UnderReview::class);

    // 1. Admin views queue
    $queueRes = $this->actingAs($this->admin)->getJson('/api/v1/admin/kyc');
    $queueRes->assertStatus(200)
        ->assertJsonFragment(['document_type' => 'national_id']);

    // 2. Admin approves
    $approveRes = $this->actingAs($this->admin)->postJson("/api/v1/admin/kyc/{$kyc->id}/approve");
    $approveRes->assertStatus(200)
        ->assertJson(['message' => 'KYC Profile Approved']);

    $kyc->refresh();
    expect($kyc->status->getValue())->toBe('approved');
    expect($kyc->reviewed_by)->toBe($this->admin->id);

    Notification::assertSentTo($this->delala, KycApproved::class);
});

it('AR-1: admin can reject KYC submission with reason and notify Delala', function () {
    Notification::fake();

    $kyc = KycProfile::create([
        'user_id' => $this->delala->id,
        'document_type' => 'kebele_id',
        'id_document_s3_key' => 'mock/kyc/id_front.jpg',
        'selfie_s3_key' => 'mock/kyc/selfie.jpg',
    ]);
    $kyc->status->transitionTo(UnderReview::class);

    $rejectRes = $this->actingAs($this->admin)->postJson("/api/v1/admin/kyc/{$kyc->id}/reject", [
        'reason' => 'The uploaded photo was blurry and unreadable.',
        'rejection_code' => 'BLURRY_DOCUMENT',
    ]);
    $rejectRes->assertStatus(200)
        ->assertJson(['message' => 'KYC Profile Rejected']);

    $kyc->refresh();
    expect($kyc->status->getValue())->toBe('rejected');
    expect($kyc->rejection_reason)->toBe('The uploaded photo was blurry and unreadable.');

    Notification::assertSentTo($this->delala, KycRejected::class);
});

it('FR-2.2: EnsureDelalaIsApproved blocks unapproved Delala but allows approved Delala', function () {
    $category = Category::factory()->create(['is_leaf' => true]);
    $job = DelalaJob::factory()->create(['category_id' => $category->id]);

    // 1. Delala without approved KYC is blocked from proposing (SRS FR-2.2)
    $response = $this->actingAs($this->delala)->postJson("/api/v1/jobs/{$job->id}/proposals", [
        'cover_letter' => 'Test',
        'items' => [
            ['item_name' => 'Search', 'commission_amount_santim' => 20000],
        ],
    ]);
    $response->assertStatus(403)
        ->assertJson(['message' => 'Your KYC profile must be approved to perform this action.']);

    // 2. Approve the Delala's KYC
    $kyc = KycProfile::create([
        'user_id' => $this->delala->id,
        'document_type' => 'passport',
        'id_document_s3_key' => 'mock/kyc/passport.jpg',
        'selfie_s3_key' => 'mock/kyc/selfie.jpg',
    ]);
    $kyc->status->transitionTo(UnderReview::class);
    $kyc->status->transitionTo(Approved::class);
    $this->delala->refresh();

    // 3. Now allowed to propose
    $responseApproved = $this->actingAs($this->delala)->postJson("/api/v1/jobs/{$job->id}/proposals", [
        'cover_letter' => 'Test',
        'items' => [
            ['item_name' => 'Search', 'commission_amount_santim' => 20000],
        ],
    ]);
    $responseApproved->assertStatus(201);
});

it('FR-2.4: Delala can register, list, setDefault, and delete payout destinations', function () {
    // 1. Register first destination (automatically becomes default)
    $createRes1 = $this->actingAs($this->delala)->postJson('/api/v1/payout-destinations', [
        'bank_code' => 'CBE',
        'bank_name' => 'Commercial Bank of Ethiopia',
        'account_number' => '1000123456789',
        'account_holder_name' => 'Abebe Delala',
    ]);
    $createRes1->assertStatus(201)
        ->assertJsonPath('data.bank_code', 'CBE')
        ->assertJsonPath('data.is_default', true);

    $dest1Id = $createRes1->json('data.id');

    // 2. Register second destination (Telebirr)
    $createRes2 = $this->actingAs($this->delala)->postJson('/api/v1/payout-destinations', [
        'bank_code' => 'TELEBIRR',
        'bank_name' => 'Telebirr',
        'account_number' => '0911000000',
        'account_holder_name' => 'Abebe Delala',
        'telebirr_number' => '0911000000',
    ]);
    $createRes2->assertStatus(201)
        ->assertJsonPath('data.is_default', false);

    $dest2Id = $createRes2->json('data.id');

    // 3. List destinations
    $listRes = $this->actingAs($this->delala)->getJson('/api/v1/payout-destinations');
    $listRes->assertStatus(200)->assertJsonCount(2);

    // 4. Set second destination as default
    $defaultRes = $this->actingAs($this->delala)->postJson("/api/v1/payout-destinations/{$dest2Id}/set-default");
    $defaultRes->assertStatus(200)
        ->assertJsonPath('data.is_default', true);

    expect(PayoutDestination::find($dest1Id)->is_default)->toBeFalse();
    expect(PayoutDestination::find($dest2Id)->is_default)->toBeTrue();

    // 5. Delete destination
    $delRes = $this->actingAs($this->delala)->deleteJson("/api/v1/payout-destinations/{$dest1Id}");
    $delRes->assertStatus(200);

    expect(PayoutDestination::find($dest1Id))->toBeNull();
});
