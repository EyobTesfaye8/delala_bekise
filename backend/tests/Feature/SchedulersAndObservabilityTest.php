<?php

use App\Models\Category;
use App\Models\DelalaJob;
use App\Models\EscrowTransaction;
use App\Models\KycProfile;
use App\Models\LedgerAccount;
use App\Models\LedgerEntry;
use App\Models\Proposal;
use App\Models\ProposalItem;
use App\Models\User;
use App\Services\ChapaService;
use App\States\Escrow\CancellationRequested;
use App\States\Escrow\HandoverDeclared;
use App\States\Escrow\PendingPayment;
use App\States\Kyc\Approved as KycApproved;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Mockery;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->category = Category::factory()->create(['is_leaf' => true]);

    $this->buyer = User::factory()->create(['type' => 'buyer', 'fcm_token' => 'buyer_device_fcm_token']);
    $this->delala = User::factory()->create(['type' => 'delala']);
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
});

it('attaches X-Request-ID to request context and response headers (NFR-OBS-1)', function () {
    // 1. Without client header: generates UUIDv4
    $response = $this->getJson('/api/v1/jobs');
    $response->assertOk();
    $requestId = $response->headers->get('X-Request-ID');
    expect($requestId)->not->toBeNull()
        ->and(Str::isUuid($requestId))->toBeTrue();

    // 2. With client header: echoes existing correlation ID
    $clientTrace = 'client-trace-12345';
    $customResponse = $this->withHeaders(['X-Request-ID' => $clientTrace])
        ->getJson('/api/v1/jobs');
    expect($customResponse->headers->get('X-Request-ID'))->toBe($clientTrace);
});

it('auto-releases escrows whose 120-hour window expired (delala:auto-release-escrow)', function () {
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
        'auto_release_at' => now()->subMinute(), // Expired!
    ]);

    $this->artisan('delala:auto-release-escrow')
        ->expectsOutputToContain('Successfully auto-released 1 expired escrow transactions.')
        ->assertSuccessful();

    expect($escrow->fresh()->status->getValue())->toBe('released');
    expect($this->job->fresh()->status->getValue())->toBe('completed');
});

it('dispatches 96-hour warning to buyer for active handovers (delala:handover-warning)', function () {
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
        'auto_release_at' => now()->addHours(12), // Expiring in 12h <= 24h
        'handover_warning_sent_at' => null,
    ]);

    $this->artisan('delala:handover-warning')
        ->expectsOutputToContain('Dispatched 1 handover 96h countdown warnings.')
        ->assertSuccessful();

    expect($escrow->fresh()->handover_warning_sent_at)->not->toBeNull();

    // Rerunning command shouldn't re-dispatch
    $this->artisan('delala:handover-warning')
        ->expectsOutputToContain('Dispatched 0 handover 96h countdown warnings.')
        ->assertSuccessful();
});

it('auto-refunds ignored pre-handover cancellations after 48 hours (delala:auto-refund-cancellations)', function () {
    $escrow = EscrowTransaction::create([
        'uuid' => (string) Str::uuid(),
        'job_id' => $this->job->id,
        'proposal_item_id' => $this->item->id,
        'buyer_id' => $this->buyer->id,
        'delala_id' => $this->delala->id,
        'commission_amount_santim' => 100000,
        'platform_fee_santim' => 5000,
        'delala_amount_santim' => 95000,
        'status' => CancellationRequested::class,
        'cancellation_deadline_at' => now()->subMinute(), // 48h deadline expired!
    ]);

    $chapaMock = Mockery::mock(ChapaService::class);
    $chapaMock->shouldReceive('refund')->once()->with(Mockery::type(EscrowTransaction::class))->andReturn(true);
    $this->app->instance(ChapaService::class, $chapaMock);

    $this->artisan('delala:auto-refund-cancellations')
        ->expectsOutputToContain('Successfully auto-refunded 1 expired cancellations.')
        ->assertSuccessful();

    expect($escrow->fresh()->status->getValue())->toBe('force_refunded');
    expect($this->job->fresh()->status->getValue())->toBe('canceled');
});

it('recovers missed webhook transactions using Chapa verify API (delala:chapa-polling-fallback)', function () {
    $this->job->update(['status' => 'negotiating']);
    $this->proposal->update(['status' => 'negotiating']);
    $this->item->update(['status' => 'pending']);

    $escrow = EscrowTransaction::create([
        'uuid' => (string) Str::uuid(),
        'job_id' => $this->job->id,
        'proposal_item_id' => $this->item->id,
        'buyer_id' => $this->buyer->id,
        'delala_id' => $this->delala->id,
        'commission_amount_santim' => 100000,
        'platform_fee_santim' => 5000,
        'delala_amount_santim' => 95000,
        'status' => PendingPayment::class,
        'chapa_tx_ref' => 'TX_MISSED_WEBHOOK_123',
    ]);

    DB::table('escrow_transactions')
        ->where('id', $escrow->id)
        ->update(['created_at' => now()->subMinutes(15)]);

    $chapaMock = Mockery::mock(ChapaService::class);
    $chapaMock->shouldReceive('verifyTransaction')
        ->once()
        ->with('TX_MISSED_WEBHOOK_123')
        ->andReturn(['status' => 'success', 'amount' => '1000.00']);
    $this->app->instance(ChapaService::class, $chapaMock);

    $this->artisan('delala:chapa-polling-fallback')
        ->expectsOutputToContain('Chapa polling completed. Verified and recovered 1 missed transactions.')
        ->assertSuccessful();

    expect($escrow->fresh()->status->getValue())->toBe('funded');
    expect($this->job->fresh()->status->getValue())->toBe('contracted');
});

it('verifies balanced double-entry ledger and alerts on discrepancy (delala:nightly-reconciliation)', function () {
    $account = LedgerAccount::create([
        'code' => 'test_account',
        'name' => 'Test Account',
        'type' => 'asset',
    ]);

    // 1. Perfectly balanced entry
    LedgerEntry::create([
        'transaction_ref' => 'BAL_1',
        'ledger_account_id' => $account->id,
        'direction' => 'debit',
        'amount_santim' => 50000,
        'description' => 'Test Debit',
    ]);
    LedgerEntry::create([
        'transaction_ref' => 'BAL_1',
        'ledger_account_id' => $account->id,
        'direction' => 'credit',
        'amount_santim' => 50000,
        'description' => 'Test Credit',
    ]);

    $this->artisan('delala:nightly-reconciliation')
        ->expectsOutputToContain('Nightly ledger reconciliation balanced.')
        ->assertSuccessful();

    // 2. Introduce artificial imbalance (Debit without matching Credit)
    LedgerEntry::create([
        'transaction_ref' => 'IMBAL_1',
        'ledger_account_id' => $account->id,
        'direction' => 'debit',
        'amount_santim' => 10000,
        'description' => 'Unbalanced Debit',
    ]);

    $this->artisan('delala:nightly-reconciliation')
        ->expectsOutputToContain('CRITICAL LEDGER IMBALANCE DETECTED!')
        ->assertFailed();
});
