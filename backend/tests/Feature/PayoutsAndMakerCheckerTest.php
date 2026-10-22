<?php

use App\Models\AdminApprovalRequest;
use App\Models\Category;
use App\Models\DelalaJob;
use App\Models\EscrowTransaction;
use App\Models\KycProfile;
use App\Models\LedgerAccount;
use App\Models\LedgerEntry;
use App\Models\PayoutDestination;
use App\Models\PayoutRequest;
use App\Models\Proposal;
use App\Models\ProposalItem;
use App\Models\User;
use App\Services\ChapaService;
use App\Services\LedgerService;
use App\States\Escrow\Disputed;
use App\States\Kyc\Approved as KycApproved;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Mockery;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->category = Category::factory()->create(['is_leaf' => true]);

    $this->buyer = User::factory()->create(['type' => 'buyer']);
    $this->delala = User::factory()->create(['type' => 'delala']);
    KycProfile::create([
        'user_id' => $this->delala->id,
        'status' => KycApproved::class,
        'national_id_front_path' => 'kyc/id_front.jpg',
        'national_id_back_path' => 'kyc/id_back.jpg',
        'selfie_path' => 'kyc/selfie.jpg',
        'reviewed_at' => now(),
    ]);

    $this->payoutDest = PayoutDestination::create([
        'delala_id' => $this->delala->id,
        'bank_code' => 'CBE',
        'bank_name' => 'Commercial Bank of Ethiopia',
        'account_number' => '1000123456789',
        'account_holder_name' => 'Abebe Delala',
        'is_default' => true,
    ]);

    $this->job = DelalaJob::factory()->create([
        'buyer_id' => $this->buyer->id,
        'category_id' => $this->category->id,
        'status' => 'contracted',
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

    $this->juniorAdmin = User::factory()->create(['type' => 'admin', 'email' => 'junior@delala.et']);
    $this->seniorAdmin = User::factory()->create(['type' => 'admin', 'email' => 'senior@delala.et']);

    // Mock ChapaService
    $this->chapaMock = Mockery::mock(ChapaService::class);
    $this->app->instance(ChapaService::class, $this->chapaMock);
});

it('enforces min 500 ETB floor and checks available balance for Delala payout request (FR-2.5)', function () {
    Sanctum::actingAs($this->delala);

    // 1. Min amount validation (50,000 santim = 500 ETB)
    $lowAmountResponse = $this->postJson('/api/v1/payouts/request', [
        'amount_santim' => 40000, // 400 ETB
    ]);
    $lowAmountResponse->assertStatus(422)
        ->assertJsonValidationErrors(['amount_santim']);

    // 2. Insufficient balance check (currently 0 santim)
    $insufficientResponse = $this->postJson('/api/v1/payouts/request', [
        'amount_santim' => 60000, // 600 ETB
    ]);
    $insufficientResponse->assertStatus(422)
        ->assertJson(['error_code' => 'INSUFFICIENT_BALANCE']);
});

it('moves funds to clearing account immediately upon withdrawal request and prevents duplicate pending requests (JNL-4A)', function () {
    // Credit Delala Available wallet with 200,000 santim (2,000 ETB)
    $account = LedgerAccount::create([
        'code' => 'delala_available_wallet_'.$this->delala->id,
        'user_id' => $this->delala->id,
        'name' => 'Available Wallet',
        'type' => 'liability',
    ]);
    LedgerEntry::create([
        'transaction_ref' => 'INIT_CREDIT',
        'ledger_account_id' => $account->id,
        'direction' => 'credit',
        'amount_santim' => 200000,
        'description' => 'Initial available balance',
    ]);

    Sanctum::actingAs($this->delala);

    // Request 1,000 ETB (100,000 santim) withdrawal
    $response = $this->postJson('/api/v1/payouts/request', [
        'amount_santim' => 100000,
    ]);

    $response->assertCreated()
        ->assertJsonStructure(['message', 'payout' => ['id', 'amount_santim', 'status']]);

    // Available balance should now be 1,000 ETB (100,000 santim)
    $ledger = app(LedgerService::class);
    expect($ledger->getDelalaAvailableBalance($this->delala->id))->toBe(100000);

    // Attempting second pending withdrawal should be blocked
    $duplicateResponse = $this->postJson('/api/v1/payouts/request', [
        'amount_santim' => 50000,
    ]);
    $duplicateResponse->assertStatus(422)
        ->assertJson(['error_code' => 'DUPLICATE_PAYOUT_REQUEST']);
});

it('allows admin to export pending payouts as bank-compatible CSV batch file (AR-3)', function () {
    PayoutRequest::create([
        'delala_id' => $this->delala->id,
        'amount_santim' => 150000,
        'bank_name' => 'Commercial Bank of Ethiopia',
        'account_number' => '1000123456789',
        'payment_method' => 'bank_transfer',
    ]);

    Sanctum::actingAs($this->juniorAdmin);

    $response = $this->get('/api/v1/admin/payouts/export-csv');

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('text/csv');
    $content = $response->streamedContent();
    expect($content)->toContain('Payout ID')
        ->toContain('Commercial Bank of Ethiopia')
        ->toContain('150000')
        ->toContain('1500.00');
});

it('allows admin to settle payout with bank reference and clears payout clearing in ledger (JNL-4B)', function () {
    $payout = PayoutRequest::create([
        'delala_id' => $this->delala->id,
        'amount_santim' => 100000,
        'bank_name' => 'Commercial Bank of Ethiopia',
        'account_number' => '1000123456789',
        'payment_method' => 'bank_transfer',
    ]);

    Sanctum::actingAs($this->juniorAdmin);

    $response = $this->postJson("/api/v1/admin/payouts/{$payout->id}/settle", [
        'bank_reference' => 'FT261022998877',
        'admin_note' => 'Processed via CBE online batch transfer',
    ]);

    $response->assertOk()
        ->assertJson(['message' => 'Payout settled successfully and recorded in double-entry ledger.']);

    expect($payout->fresh()->status->getValue())->toBe('paid')
        ->and($payout->fresh()->processed_by)->toBe($this->juniorAdmin->id)
        ->and($payout->fresh()->paid_at)->not->toBeNull();

    // Verify JNL-4B entry written
    $settledEntry = LedgerEntry::where('transaction_ref', 'like', 'WSET_%')->first();
    expect($settledEntry)->not->toBeNull()
        ->and($settledEntry->amount_santim)->toBe(100000);
});

it('executes force release immediately when amount is <= 10,000 ETB (Maker-Checker threshold)', function () {
    $escrow = EscrowTransaction::create([
        'uuid' => (string) Str::uuid(),
        'job_id' => $this->job->id,
        'proposal_item_id' => $this->item->id,
        'buyer_id' => $this->buyer->id,
        'delala_id' => $this->delala->id,
        'commission_amount_santim' => 500000, // 5,000 ETB <= 10,000 ETB threshold
        'platform_fee_santim' => 25000,
        'delala_amount_santim' => 475000,
        'status' => Disputed::class,
    ]);

    Sanctum::actingAs($this->juniorAdmin);

    $response = $this->postJson("/api/v1/admin/escrow/{$escrow->id}/force-release", [
        'justification_note' => 'Delala provided valid signed handover agreement.',
    ]);

    $response->assertOk()
        ->assertJson(['message' => 'Funds force-released to Delala available wallet successfully.']);

    expect($escrow->fresh()->status->getValue())->toBe('force_released')
        ->and($this->job->fresh()->status->getValue())->toBe('completed');
});

it('queues Maker-Checker approval request when force release/refund exceeds 10,000 ETB (SRS §3 & AR-2)', function () {
    $escrow = EscrowTransaction::create([
        'uuid' => (string) Str::uuid(),
        'job_id' => $this->job->id,
        'proposal_item_id' => $this->item->id,
        'buyer_id' => $this->buyer->id,
        'delala_id' => $this->delala->id,
        'commission_amount_santim' => 2000000, // 20,000 ETB > 10,000 ETB threshold
        'platform_fee_santim' => 100000,
        'delala_amount_santim' => 1900000,
        'status' => Disputed::class,
    ]);

    Sanctum::actingAs($this->juniorAdmin);

    $response = $this->postJson("/api/v1/admin/escrow/{$escrow->id}/force-release", [
        'justification_note' => 'High value transaction disputed, delala presented full verified paper contract.',
    ]);

    $response->assertStatus(202)
        ->assertJson([
            'requires_maker_checker' => true,
        ]);

    $approval = AdminApprovalRequest::where('entity_id', $escrow->id)->first();
    expect($approval)->not->toBeNull()
        ->and($approval->status)->toBe('PENDING')
        ->and($approval->requested_by_id)->toBe($this->juniorAdmin->id);

    // Escrow status must not have changed yet!
    expect($escrow->fresh()->status->getValue())->toBe('disputed');
});

it('enforces Maker-Checker segregation preventing maker from approving their own request (SRS §3)', function () {
    $approval = AdminApprovalRequest::create([
        'action_type' => 'FORCE_RELEASE',
        'entity_type' => EscrowTransaction::class,
        'entity_id' => 999,
        'amount_santim' => 1500000,
        'requested_by_id' => $this->juniorAdmin->id,
        'status' => 'PENDING',
        'justification_note' => 'Test high value release',
    ]);

    // Junior Admin attempts to approve their own request
    Sanctum::actingAs($this->juniorAdmin);
    $response = $this->postJson("/api/v1/admin/approvals/{$approval->id}/approve");

    $response->assertStatus(403)
        ->assertJson(['error_code' => 'MAKER_CHECKER_VIOLATION']);
});

it('allows a secondary admin to approve Maker-Checker request and dispatches to ledger', function () {
    $escrow = EscrowTransaction::create([
        'uuid' => (string) Str::uuid(),
        'job_id' => $this->job->id,
        'proposal_item_id' => $this->item->id,
        'buyer_id' => $this->buyer->id,
        'delala_id' => $this->delala->id,
        'commission_amount_santim' => 2000000,
        'platform_fee_santim' => 100000,
        'delala_amount_santim' => 1900000,
        'status' => Disputed::class,
    ]);

    $approval = AdminApprovalRequest::create([
        'action_type' => 'FORCE_RELEASE',
        'entity_type' => EscrowTransaction::class,
        'entity_id' => $escrow->id,
        'amount_santim' => 1900000,
        'requested_by_id' => $this->juniorAdmin->id,
        'status' => 'PENDING',
        'justification_note' => 'Approved by Junior Admin, needs Senior Admin sign-off',
    ]);

    // Senior Admin approves
    Sanctum::actingAs($this->seniorAdmin);
    $response = $this->postJson("/api/v1/admin/approvals/{$approval->id}/approve");

    $response->assertOk()
        ->assertJson(['message' => 'Maker-Checker approval confirmed and action dispatched to ledger.']);

    expect($approval->fresh()->status)->toBe('APPROVED')
        ->and($approval->fresh()->approved_by_id)->toBe($this->seniorAdmin->id);

    expect($escrow->fresh()->status->getValue())->toBe('force_released');
    expect($this->job->fresh()->status->getValue())->toBe('completed');
});

it('executes user suspension kill-switch terminating sessions and withdrawing pending proposals (AR-4)', function () {
    $proposal = Proposal::factory()->create([
        'delala_id' => $this->delala->id,
        'status' => 'pending',
    ]);

    // Give user a Sanctum token
    $token = $this->delala->createToken('test_token');
    expect($this->delala->tokens()->count())->toBe(1);

    Sanctum::actingAs($this->seniorAdmin);

    $response = $this->postJson("/api/v1/admin/users/{$this->delala->id}/suspend", [
        'reason' => 'Repeated attempts to bypass platform escrow.',
    ]);

    $response->assertOk();

    // Verify token revoked, status suspended, and proposal withdrawn
    expect($this->delala->fresh()->tokens()->count())->toBe(0);
    expect($this->delala->fresh()->status)->toBe('suspended');
    expect($proposal->fresh()->status->getValue())->toBe('withdrawn');
});
