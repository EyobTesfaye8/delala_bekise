<?php

use App\Models\Category;
use App\Models\Chat;
use App\Models\DelalaJob;
use App\Models\EscrowTransaction;
use App\Models\KycProfile;
use App\Models\LedgerEntry;
use App\Models\Proposal;
use App\Models\ProposalItem;
use App\Models\User;
use App\Services\ChapaService;
use App\States\Escrow\Funded;
use App\States\Escrow\HandoverDeclared;
use App\States\Kyc\Approved as KycApproved;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Mockery;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->category = Category::factory()->create(['is_leaf' => true]);

    $this->buyer = User::factory()->create(['type' => 'buyer', 'phone' => '+251911111111']);
    $this->delala = User::factory()->create(['type' => 'delala', 'phone' => '+251922222222']);
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
        'status' => 'negotiating',
    ]);

    $this->proposal = Proposal::factory()->create([
        'job_id' => $this->job->id,
        'delala_id' => $this->delala->id,
        'status' => 'negotiating',
    ]);

    $this->item = ProposalItem::factory()->create([
        'proposal_id' => $this->proposal->id,
        'commission_amount_santim' => 100000, // 1,000 ETB
        'status' => 'pending',
    ]);

    // Mock ChapaService
    $this->chapaMock = Mockery::mock(ChapaService::class);
    $this->app->instance(ChapaService::class, $this->chapaMock);
});

it('initiates escrow and generates Chapa checkout URL on proposal item acceptance (SRS §4.3)', function () {
    Sanctum::actingAs($this->buyer);

    $this->chapaMock->shouldReceive('generateCheckoutUrl')
        ->once()
        ->andReturn('https://checkout.chapa.co/checkout/test_12345');

    $response = $this->postJson("/api/v1/proposals/{$this->proposal->id}/items/{$this->item->id}/accept");

    $response->assertOk()
        ->assertJson([
            'message' => 'Item accepted, please proceed to payment.',
            'checkout_url' => 'https://checkout.chapa.co/checkout/test_12345',
        ]);

    $escrow = EscrowTransaction::where('proposal_item_id', $this->item->id)->first();
    expect($escrow)->not->toBeNull()
        ->and($escrow->commission_amount_santim)->toBe(100000)
        ->and($escrow->platform_fee_santim)->toBe(5000) // 5%
        ->and($escrow->delala_amount_santim)->toBe(95000); // 95%
});

it('verifies Chapa HMAC webhook signature and funds escrow with double-entry ledger entries (SRS §5.1, §5.2)', function () {
    $escrow = EscrowTransaction::create([
        'uuid' => (string) Str::uuid(),
        'job_id' => $this->job->id,
        'proposal_item_id' => $this->item->id,
        'buyer_id' => $this->buyer->id,
        'delala_id' => $this->delala->id,
        'commission_amount_santim' => 100000,
        'platform_fee_santim' => 5000,
        'delala_amount_santim' => 95000,
        'chapa_tx_ref' => 'TX_SUCCESS_123',
    ]);

    // Secondary competing proposal on same job
    $delala2 = User::factory()->create(['type' => 'delala']);
    $otherProposal = Proposal::factory()->create([
        'job_id' => $this->job->id,
        'delala_id' => $delala2->id,
        'status' => 'negotiating',
    ]);
    $otherChat = Chat::create([
        'proposal_id' => $otherProposal->id,
        'buyer_id' => $this->buyer->id,
        'delala_id' => $delala2->id,
        'is_read_only' => false,
    ]);

    $this->chapaMock->shouldReceive('verifyWebhookSignature')->andReturn(true);

    $payload = [
        'event' => 'charge.success',
        'tx_ref' => 'TX_SUCCESS_123',
        'amount' => '1000.00',
        'currency' => 'ETB',
    ];

    $response = $this->withHeaders(['x-chapa-signature' => 'valid_sig'])
        ->postJson('/api/v1/escrow/chapa-callback', $payload);

    $response->assertOk();

    // Verify states
    expect($escrow->fresh()->status->getValue())->toBe('funded');
    expect($this->job->fresh()->status->getValue())->toBe('contracted');
    expect($this->proposal->fresh()->status->getValue())->toBe('accepted');
    expect($otherProposal->fresh()->status->getValue())->toBe('rejected');
    expect($otherChat->fresh()->is_read_only)->toBeTrue();

    // Verify double-entry ledger journals (JNL-1A & JNL-1B)
    $ledgerEntries = LedgerEntry::where('escrow_transaction_id', $escrow->id)->get();
    expect($ledgerEntries->count())->toBe(5);

    $totalDebits = $ledgerEntries->where('direction', 'debit')->sum('amount_santim');
    $totalCredits = $ledgerEntries->where('direction', 'credit')->sum('amount_santim');
    expect($totalDebits)->toBe($totalCredits); // Perfectly balanced double-entry
});

it('triggers automatic overpayment refund when secondary payment hits already contracted job (SRS §10.4)', function () {
    $this->job->update(['status' => 'contracted']);

    $secondaryEscrow = EscrowTransaction::create([
        'uuid' => (string) Str::uuid(),
        'job_id' => $this->job->id,
        'proposal_item_id' => $this->item->id,
        'buyer_id' => $this->buyer->id,
        'delala_id' => $this->delala->id,
        'commission_amount_santim' => 100000,
        'platform_fee_santim' => 5000,
        'delala_amount_santim' => 95000,
        'chapa_tx_ref' => 'TX_SECONDARY_RACE',
    ]);

    $this->chapaMock->shouldReceive('verifyWebhookSignature')->andReturn(true);
    $this->chapaMock->shouldReceive('refund')->once()->with(Mockery::type(EscrowTransaction::class))->andReturn(true);

    $payload = [
        'event' => 'charge.success',
        'tx_ref' => 'TX_SECONDARY_RACE',
    ];

    $response = $this->withHeaders(['x-chapa-signature' => 'valid_sig'])
        ->postJson('/api/v1/escrow/chapa-callback', $payload);

    $response->assertOk();
    expect($secondaryEscrow->fresh()->status->getValue())->toBe('force_refunded');
});

it('executes handover declaration and buyer fund release workflow (FR-5.1)', function () {
    $this->job->update(['status' => 'contracted']);

    $escrow = EscrowTransaction::create([
        'uuid' => (string) Str::uuid(),
        'job_id' => $this->job->id,
        'proposal_item_id' => $this->item->id,
        'buyer_id' => $this->buyer->id,
        'delala_id' => $this->delala->id,
        'commission_amount_santim' => 100000,
        'platform_fee_santim' => 5000,
        'delala_amount_santim' => 95000,
        'status' => Funded::class,
    ]);

    // Delala declares handover
    Sanctum::actingAs($this->delala);
    $handoverResponse = $this->postJson("/api/v1/escrow/{$escrow->id}/handover");
    $handoverResponse->assertOk();

    expect($escrow->fresh()->status->getValue())->toBe('handover_declared')
        ->and($escrow->fresh()->auto_release_at)->not->toBeNull();

    // Buyer releases funds
    Sanctum::actingAs($this->buyer);
    $releaseResponse = $this->postJson("/api/v1/escrow/{$escrow->id}/release");
    $releaseResponse->assertOk()
        ->assertJson(['message' => 'Funds released successfully.']);

    expect($escrow->fresh()->status->getValue())->toBe('released')
        ->and($this->job->fresh()->status->getValue())->toBe('completed');

    // Verify JNL-2 written to ledger
    $delalaAvailableEntries = LedgerEntry::where('direction', 'credit')
        ->where('description', 'like', '%Escrow released%')
        ->get();
    expect($delalaAvailableEntries->count())->toBeGreaterThanOrEqual(1);
});

it('handles pre-handover cancellation workflow (SRS §4.3 & §7.5)', function () {
    $this->job->update(['status' => 'contracted']);

    $escrow = EscrowTransaction::create([
        'uuid' => (string) Str::uuid(),
        'job_id' => $this->job->id,
        'proposal_item_id' => $this->item->id,
        'buyer_id' => $this->buyer->id,
        'delala_id' => $this->delala->id,
        'commission_amount_santim' => 100000,
        'platform_fee_santim' => 5000,
        'delala_amount_santim' => 95000,
        'status' => Funded::class,
    ]);

    // Buyer requests cancellation
    Sanctum::actingAs($this->buyer);
    $cancelReqResponse = $this->postJson("/api/v1/escrow/{$escrow->id}/cancel-request", [
        'reason' => 'Changed my mind before inspection.',
    ]);

    $cancelReqResponse->assertOk();
    expect($escrow->fresh()->status->getValue())->toBe('cancellation_requested')
        ->and($escrow->fresh()->cancellation_deadline_at)->not->toBeNull();

    // Delala approves cancellation -> triggers Chapa refund and JNL-3 reversal
    Sanctum::actingAs($this->delala);
    $this->chapaMock->shouldReceive('refund')->once()->with(Mockery::type(EscrowTransaction::class))->andReturn(true);

    $approveResponse = $this->postJson("/api/v1/escrow/{$escrow->id}/cancel-approve");
    $approveResponse->assertOk();

    expect($escrow->fresh()->status->getValue())->toBe('force_refunded');
    expect($this->job->fresh()->status->getValue())->toBe('canceled');
});

it('handles dispute initiation and third-party seller evidence upload (FR-5.2 & FR-5.3)', function () {
    $escrow = EscrowTransaction::create([
        'uuid' => (string) Str::uuid(),
        'job_id' => $this->job->id,
        'proposal_item_id' => $this->item->id,
        'buyer_id' => $this->buyer->id,
        'delala_id' => $this->delala->id,
        'commission_amount_santim' => 100000,
        'platform_fee_santim' => 5000,
        'delala_amount_santim' => 95000,
        'status' => HandoverDeclared::class,
    ]);

    // Buyer disputes handover
    Sanctum::actingAs($this->buyer);
    $disputeResponse = $this->postJson("/api/v1/escrow/{$escrow->id}/dispute", [
        'reason' => 'The property owner never showed up for lease signing.',
        'reason_code' => 'SELLER_NO_SHOW',
    ]);

    $disputeResponse->assertOk();
    expect($escrow->fresh()->status->getValue())->toBe('disputed')
        ->and($escrow->fresh()->dispute_reason_code)->toBe('SELLER_NO_SHOW');

    // Delala uploads evidence of transfer to third-party seller
    Sanctum::actingAs($this->delala);
    $evidenceResponse = $this->postJson("/api/v1/escrow/{$escrow->id}/dispute-evidence", [
        'evidence_keys' => ['evidence/cbe_buyer_to_seller_receipt.pdf'],
        'notes' => 'Attached transfer proof between Buyer Abebe and Property Owner Chala.',
    ]);

    $evidenceResponse->assertOk()
        ->assertJsonStructure(['message', 'dispute_evidence_s3_keys']);

    expect($escrow->fresh()->dispute_evidence_s3_keys)->toContain('evidence/cbe_buyer_to_seller_receipt.pdf');
});
