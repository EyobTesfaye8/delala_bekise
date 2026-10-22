<?php

use App\Events\ChatMessageSent;
use App\Events\CommissionUpdated;
use App\Models\Category;
use App\Models\Chat;
use App\Models\DelalaJob;
use App\Models\KycProfile;
use App\Models\Proposal;
use App\Models\ProposalItem;
use App\Models\ProposalItemPriceHistory;
use App\Models\User;
use App\States\Kyc\Approved as KycApproved;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->category = Category::factory()->create(['is_leaf' => true]);

    $this->buyer = User::factory()->create(['type' => 'buyer', 'phone' => '+251911111111']);
    $this->verifiedDelala = User::factory()->create(['type' => 'delala', 'phone' => '+251922222222']);
    KycProfile::create([
        'user_id' => $this->verifiedDelala->id,
        'status' => KycApproved::class,
        'national_id_front_path' => 'kyc/id_front.jpg',
        'national_id_back_path' => 'kyc/id_back.jpg',
        'selfie_path' => 'kyc/selfie.jpg',
        'reviewed_at' => now(),
    ]);

    $this->unverifiedDelala = User::factory()->create(['type' => 'delala', 'phone' => '+251933333333']);

    $this->job = DelalaJob::factory()->create([
        'buyer_id' => $this->buyer->id,
        'category_id' => $this->category->id,
        'status' => 'open',
    ]);
});

it('prevents unverified delalas from submitting proposals (FR-2.2)', function () {
    Sanctum::actingAs($this->unverifiedDelala);

    $response = $this->postJson("/api/v1/jobs/{$this->job->id}/proposals", [
        'cover_letter' => 'I can find you this house.',
        'items' => [
            ['item_name' => 'Apartment search', 'commission_amount_santim' => 20000],
        ],
    ]);

    $response->assertStatus(403)
        ->assertJson(['message' => 'Your KYC profile must be approved to perform this action.']);
});

it('enforces min commission floor of 10,000 santim per proposal item (SRS §6.1)', function () {
    Sanctum::actingAs($this->verifiedDelala);

    $response = $this->postJson("/api/v1/jobs/{$this->job->id}/proposals", [
        'cover_letter' => 'I will assist you.',
        'items' => [
            ['item_name' => 'Low commission item', 'commission_amount_santim' => 5000], // 50 ETB < 100 ETB
        ],
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['items.0.commission_amount_santim']);
});

it('allows verified delala to submit a proposal and prevents duplicate active proposals (FR-4.1)', function () {
    Sanctum::actingAs($this->verifiedDelala);

    $payload = [
        'cover_letter' => 'I have 3 verified properties matching your request.',
        'items' => [
            [
                'item_name' => 'Property Scouting',
                'description' => 'Will arrange 3 property viewings',
                'commission_amount_santim' => 50000, // 500 ETB
            ],
        ],
    ];

    $response = $this->postJson("/api/v1/jobs/{$this->job->id}/proposals", $payload);

    $response->assertCreated()
        ->assertJsonStructure(['message', 'proposal' => ['id', 'items']]);

    expect(Proposal::where('job_id', $this->job->id)->where('delala_id', $this->verifiedDelala->id)->count())->toBe(1);

    // Attempt second proposal on same job
    $duplicateResponse = $this->postJson("/api/v1/jobs/{$this->job->id}/proposals", $payload);
    $duplicateResponse->assertStatus(422)
        ->assertJson([
            'error_code' => 'DUPLICATE_PROPOSAL',
        ]);
});

it('allows broker to withdraw pending proposal (FR-4.2)', function () {
    Sanctum::actingAs($this->verifiedDelala);

    $proposal = Proposal::factory()->create([
        'job_id' => $this->job->id,
        'delala_id' => $this->verifiedDelala->id,
        'status' => 'pending',
    ]);

    $response = $this->postJson("/api/v1/proposals/{$proposal->id}/withdraw");
    $response->assertOk()
        ->assertJson(['message' => 'Proposal withdrawn']);

    expect($proposal->fresh()->status->getValue())->toBe('withdrawn');
});

it('allows buyer to reject proposal and marks chat read-only (FR-4.2)', function () {
    Sanctum::actingAs($this->buyer);

    $proposal = Proposal::factory()->create([
        'job_id' => $this->job->id,
        'delala_id' => $this->verifiedDelala->id,
        'status' => 'pending',
    ]);

    $chat = Chat::create([
        'proposal_id' => $proposal->id,
        'buyer_id' => $this->buyer->id,
        'delala_id' => $this->verifiedDelala->id,
        'is_read_only' => false,
    ]);

    $response = $this->postJson("/api/v1/proposals/{$proposal->id}/reject");
    $response->assertOk()
        ->assertJson(['message' => 'Proposal rejected']);

    expect($proposal->fresh()->status->getValue())->toBe('rejected');
    expect($chat->fresh()->is_read_only)->toBeTrue();
});

it('mutates item commission in-chat and records price history audit trail (FR-4.4)', function () {
    Event::fake([CommissionUpdated::class]);

    Sanctum::actingAs($this->verifiedDelala);

    $proposal = Proposal::factory()->create([
        'job_id' => $this->job->id,
        'delala_id' => $this->verifiedDelala->id,
        'status' => 'negotiating',
    ]);

    $item = ProposalItem::factory()->create([
        'proposal_id' => $proposal->id,
        'commission_amount_santim' => 50000,
        'status' => 'pending',
    ]);

    $response = $this->patchJson("/api/v1/proposals/{$proposal->id}/items/{$item->id}/commission", [
        'commission_amount_santim' => 40000,
    ]);

    $response->assertOk()
        ->assertJson([
            'message' => 'Commission updated successfully',
            'old_amount_santim' => 50000,
            'new_amount_santim' => 40000,
        ]);

    expect($item->fresh()->commission_amount_santim)->toBe(40000);

    // Verify audit record in proposal_item_price_histories
    $history = ProposalItemPriceHistory::where('proposal_item_id', $item->id)->first();
    expect($history)->not->toBeNull()
        ->and($history->old_amount_santim)->toBe(50000)
        ->and($history->new_amount_santim)->toBe(40000)
        ->and($history->changed_by_id)->toBe($this->verifiedDelala->id);

    Event::assertDispatched(CommissionUpdated::class);
});

it('enforces buyer-first chat initiation gate (FR-4.3)', function () {
    $proposal = Proposal::factory()->create([
        'job_id' => $this->job->id,
        'delala_id' => $this->verifiedDelala->id,
        'status' => 'pending',
    ]);

    // Delala attempts to initiate chat before buyer engages
    Sanctum::actingAs($this->verifiedDelala);
    $delalaInitResponse = $this->getJson("/api/v1/proposals/{$proposal->id}/chat");
    $delalaInitResponse->assertStatus(403)
        ->assertJson(['message' => 'The buyer must initiate the negotiation chat first.']);

    // Buyer initiates chat
    Sanctum::actingAs($this->buyer);
    $buyerInitResponse = $this->getJson("/api/v1/proposals/{$proposal->id}/chat");
    $buyerInitResponse->assertOk()
        ->assertJsonStructure(['chat' => ['id', 'proposal_id', 'buyer_id', 'delala_id']])
        ->assertJson([
            'is_contracted' => false,
            'contact_details' => null,
        ]);

    // Now proposal and job transitioned to negotiating
    expect($proposal->fresh()->status->getValue())->toBe('negotiating');
    expect($this->job->fresh()->status->getValue())->toBe('negotiating');

    // Delala can now access the instantiated chat
    Sanctum::actingAs($this->verifiedDelala);
    $delalaFollowResponse = $this->getJson("/api/v1/proposals/{$proposal->id}/chat");
    $delalaFollowResponse->assertOk();
});

it('blocks off-platform contact sharing before escrow funding (FR-4.5)', function () {
    Sanctum::actingAs($this->verifiedDelala);

    $proposal = Proposal::factory()->create([
        'job_id' => $this->job->id,
        'delala_id' => $this->verifiedDelala->id,
        'status' => 'negotiating',
    ]);

    $chat = Chat::create([
        'proposal_id' => $proposal->id,
        'buyer_id' => $this->buyer->id,
        'delala_id' => $this->verifiedDelala->id,
        'is_read_only' => false,
    ]);

    // Attempt sharing phone number
    $phoneResponse = $this->postJson("/api/v1/chats/{$chat->id}/messages", [
        'type' => 'text',
        'body' => 'Call me directly at 0911234567 to discuss.',
    ]);

    $phoneResponse->assertStatus(422)
        ->assertJson([
            'error_code' => 'CIRCUMVENTION_DETECTED',
            'strike_count' => 1,
        ]);

    expect($this->verifiedDelala->fresh()->circumvention_strike_count)->toBe(1);

    // Attempt sharing Telegram handle
    $telegramResponse = $this->postJson("/api/v1/chats/{$chat->id}/messages", [
        'type' => 'text',
        'body' => 'Hit me up on t.me/delalabroker for faster reply.',
    ]);

    $telegramResponse->assertStatus(422)
        ->assertJson([
            'error_code' => 'CIRCUMVENTION_DETECTED',
            'strike_count' => 2,
        ]);

    expect($this->verifiedDelala->fresh()->circumvention_strike_count)->toBe(2);

    // Valid professional message succeeds
    Event::fake([ChatMessageSent::class]);
    $validResponse = $this->postJson("/api/v1/chats/{$chat->id}/messages", [
        'type' => 'text',
        'body' => 'I have verified 2 properties that match all your requirements.',
    ]);

    $validResponse->assertCreated()
        ->assertJsonPath('data.body', 'I have verified 2 properties that match all your requirements.');

    Event::assertDispatched(ChatMessageSent::class);
});
