<?php

use App\Models\User;
use App\Models\Category;
use App\Models\DelalaJob;
use App\Models\Proposal;
use App\Models\ProposalItem;
use App\Models\EscrowTransaction;
use App\Models\LedgerEntry;
use App\Services\EscrowService;
use App\Services\LedgerService;
use App\Services\ChapaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Mockery;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->buyer = User::factory()->create(['type' => 'buyer']);
    $this->delala1 = User::factory()->create(['type' => 'delala']);
    $this->delala2 = User::factory()->create(['type' => 'delala']);
    $this->category = Category::factory()->create();
    
    $this->job = DelalaJob::factory()->create([
        'buyer_id' => $this->buyer->id,
        'category_id' => $this->category->id,
        'status' => 'negotiating',
    ]);
    
    $this->proposal1 = Proposal::factory()->create([
        'job_id' => $this->job->id,
        'delala_id' => $this->delala1->id,
        'status' => 'negotiating'
    ]);
    
    $this->proposalItem1 = ProposalItem::factory()->create([
        'proposal_id' => $this->proposal1->id,
        'commission_amount_santim' => 100000, // 1,000 ETB
        'status' => 'pending'
    ]);
    
    $this->proposal2 = Proposal::factory()->create([
        'job_id' => $this->job->id,
        'delala_id' => $this->delala2->id,
        'status' => 'negotiating'
    ]);
    
    $this->proposalItem2 = ProposalItem::factory()->create([
        'proposal_id' => $this->proposal2->id,
        'item_name' => 'Item 2',
        'commission_amount_santim' => 200000, // 2,000 ETB
        'status' => 'pending'
    ]);
    
    // Mock Chapa Service
    $this->chapaMock = Mockery::mock(ChapaService::class);
    $this->chapaMock->shouldReceive('generateCheckoutUrl')->andReturnUsing(function ($escrow) {
        $txRef = 'tx_' . $escrow->id;
        $escrow->update(['chapa_tx_ref' => $txRef, 'chapa_checkout_url' => 'https://checkout.chapa.co/checkout/fake']);
        return 'https://checkout.chapa.co/checkout/fake';
    });
    $this->chapaMock->shouldReceive('refund')->andReturn(true);
    app()->instance(ChapaService::class, $this->chapaMock);
});

it('follows SRS 3.1: First-to-Fund Wins and voids other escrows', function () {
    // Both buyers are negotiating and get an escrow checkout link
    $escrowService = app(EscrowService::class);
    
    $escrowService->initiateEscrow($this->proposalItem1, $this->buyer->id);
    $escrowService->initiateEscrow($this->proposalItem2, $this->buyer->id);
    
    $escrow1 = EscrowTransaction::where('proposal_item_id', $this->proposalItem1->id)->first();
    $escrow2 = EscrowTransaction::where('proposal_item_id', $this->proposalItem2->id)->first();
    
    expect($escrow1->status->getValue())->toBe('pending_payment');
    expect($escrow2->status->getValue())->toBe('pending_payment');
    
    // Webhook 1 arrives (First to fund)
    $escrowService->handlePaymentSuccess($escrow1->chapa_tx_ref, ['tx_ref' => $escrow1->chapa_tx_ref]);
    
    $this->job->refresh();
    expect($this->job->status->getValue())->toBe('contracted');
    
    // The proposal 1 item should be accepted, other rejected
    $this->proposal1->refresh();
    expect($this->proposal1->status->getValue())->toBe('accepted');
    
    // Webhook 2 arrives (Late to fund)
    // The system should trigger a refund for this escrow
    $escrowService->handlePaymentSuccess($escrow2->chapa_tx_ref, ['tx_ref' => $escrow2->chapa_tx_ref]);
    
    $escrow2->refresh();
    // It should have transitioned to force_refunded or refund_failed
    expect(['force_refunded', 'refund_failed'])->toContain($escrow2->status->getValue());
});

it('follows SRS 4.2: Standard Journal Entries for Flow A (Webhook Received & Provisional Allocation)', function () {
    $escrowService = app(EscrowService::class);
    $escrowService->initiateEscrow($this->proposalItem1, $this->buyer->id);
    $escrow = EscrowTransaction::where('proposal_item_id', $this->proposalItem1->id)->first();
    
    // Commission is 100,000 santim. Platform fee is 5,000. Delala gets 95,000.
    $escrowService->handlePaymentSuccess($escrow->chapa_tx_ref, ['tx_ref' => $escrow->chapa_tx_ref]);
    
    // Verify Ledger Entries
    // 1. Webhook Received
    // DR system_chapa_holding
    // CR buyer_escrow_liability
    $webhookDebit = LedgerEntry::where('escrow_transaction_id', $escrow->id)
        ->whereHas('account', fn($q) => $q->where('code', 'system_chapa_holding'))
        ->where('direction', 'debit')
        ->first();
        
    $webhookCredit = LedgerEntry::where('escrow_transaction_id', $escrow->id)
        ->whereHas('account', fn($q) => $q->where('code', 'buyer_escrow_liability'))
        ->where('direction', 'credit')
        ->where('amount_santim', 100000)
        ->first();
        
    expect($webhookDebit)->not->toBeNull();
    expect($webhookDebit->amount_santim)->toBe(100000);
    expect($webhookCredit)->not->toBeNull();
    
    // 2. Provisional Allocation
    // DR buyer_escrow_liability (100,000)
    // CR delala_pending_wallet (95,000)
    // CR platform_revenue_wallet (5,000)
    $allocDebit = LedgerEntry::where('escrow_transaction_id', $escrow->id)
        ->whereHas('account', fn($q) => $q->where('code', 'buyer_escrow_liability'))
        ->where('direction', 'debit')
        ->where('amount_santim', 100000)
        ->first();
        
    $delalaCredit = LedgerEntry::where('escrow_transaction_id', $escrow->id)
        ->whereHas('account', fn($q) => $q->where('code', 'delala_pending_wallet'))
        ->where('direction', 'credit')
        ->first();
        
    $platformCredit = LedgerEntry::where('escrow_transaction_id', $escrow->id)
        ->whereHas('account', fn($q) => $q->where('code', 'platform_revenue_wallet'))
        ->where('direction', 'credit')
        ->first();
        
    expect($allocDebit)->not->toBeNull();
    expect($delalaCredit)->not->toBeNull();
    expect($delalaCredit->amount_santim)->toBe(95000);
    expect($platformCredit)->not->toBeNull();
    expect($platformCredit->amount_santim)->toBe(5000);
});
