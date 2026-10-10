<?php

namespace App\Services;

use App\Models\EscrowTransaction;
use App\Models\LedgerAccount;
use App\Models\LedgerEntry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class LedgerService
{
    /**
     * Get or create a system ledger account.
     */
    public function getSystemAccount(string $code, string $name, string $type): LedgerAccount
    {
        return LedgerAccount::firstOrCreate(
            ['code' => $code],
            ['name' => $name, 'type' => $type]
        );
    }

    /**
     * Flow A: Escrow Funded (Webhook Received & Provisional Allocation)
     */
    public function recordEscrowFunded(EscrowTransaction $escrow): void
    {
        DB::transaction(function () use ($escrow) {
            // Lock escrow
            $escrow = EscrowTransaction::where('id', $escrow->id)->lockForUpdate()->firstOrFail();

            $chapaHolding = $this->getSystemAccount('system_chapa_holding', 'System Chapa Holding', 'asset');
            $buyerLiability = $this->getSystemAccount('buyer_escrow_liability', 'Buyer Escrow Liability', 'liability');
            $platformRevenue = $this->getSystemAccount('platform_revenue_wallet', 'Platform Revenue Wallet', 'equity');
            $delalaPending = $this->getSystemAccount('delala_pending_wallet', 'Delala Pending Wallet', 'liability');

            $txRef1 = 'FND_'.Str::random(10);

            // 1. Webhook Received
            $this->createEntry($txRef1, $chapaHolding, 'debit', $escrow->commission_amount_santim, 'Escrow funded - '.$escrow->uuid, $escrow->id);
            $this->createEntry($txRef1, $buyerLiability, 'credit', $escrow->commission_amount_santim, 'Escrow funded - '.$escrow->uuid, $escrow->id);

            $txRef2 = 'ALLOC_'.Str::random(10);

            // 2. Provisional Allocation
            $this->createEntry($txRef2, $buyerLiability, 'debit', $escrow->commission_amount_santim, 'Provisional allocation - '.$escrow->uuid, $escrow->id);
            $this->createEntry($txRef2, $delalaPending, 'credit', $escrow->delala_amount_santim, 'Provisional allocation - '.$escrow->uuid, $escrow->id);
            $this->createEntry($txRef2, $platformRevenue, 'credit', $escrow->platform_fee_santim, 'Provisional allocation fee - '.$escrow->uuid, $escrow->id);
        });
    }

    /**
     * Flow B: Escrow Released (to Delala's Available Wallet)
     */
    public function recordEscrowReleased(EscrowTransaction $escrow): void
    {
        DB::transaction(function () use ($escrow) {
            $escrow = EscrowTransaction::where('id', $escrow->id)->lockForUpdate()->firstOrFail();

            $delalaPending = $this->getSystemAccount('delala_pending_wallet', 'Delala Pending Wallet', 'liability');

            // Delala's specific available wallet
            $delalaAvailable = LedgerAccount::firstOrCreate(
                ['code' => 'delala_available_wallet_'.$escrow->delala_id, 'user_id' => $escrow->delala_id],
                ['name' => 'Available Wallet - '.$escrow->delala_id, 'type' => 'liability']
            );

            $txRef = 'REL_'.Str::random(10);

            $this->createEntry($txRef, $delalaPending, 'debit', $escrow->delala_amount_santim, 'Escrow released - '.$escrow->uuid, $escrow->id);
            $this->createEntry($txRef, $delalaAvailable, 'credit', $escrow->delala_amount_santim, 'Escrow released - '.$escrow->uuid, $escrow->id);
        });
    }

    /**
     * Flow C: Any Refund Path (Force, Auto, Secondary Buyer)
     */
    public function recordRefund(EscrowTransaction $escrow, string $reason = 'Refund'): void
    {
        DB::transaction(function () use ($escrow, $reason) {
            $escrow = EscrowTransaction::where('id', $escrow->id)->lockForUpdate()->firstOrFail();

            $systemChapa = $this->getSystemAccount('system_chapa_holding', 'System Chapa Holding', 'asset');
            $delalaPending = $this->getSystemAccount('delala_pending_wallet', 'Delala Pending Wallet', 'liability');
            $platformRevenue = $this->getSystemAccount('platform_revenue_wallet', 'Platform Revenue Wallet', 'equity');

            $txRef = 'REF_'.Str::random(10);

            $this->createEntry($txRef, $delalaPending, 'debit', $escrow->delala_amount_santim, $reason.' - '.$escrow->uuid, $escrow->id);
            $this->createEntry($txRef, $platformRevenue, 'debit', $escrow->platform_fee_santim, $reason.' fee reversal - '.$escrow->uuid, $escrow->id);
            $this->createEntry($txRef, $systemChapa, 'credit', $escrow->commission_amount_santim, $reason.' - '.$escrow->uuid, $escrow->id);
        });
    }

    /**
     * Flow D: Delala Withdrawal (PAID)
     */
    public function recordWithdrawal(int $delalaId, int $amountSantim, string $payoutRef): void
    {
        DB::transaction(function () use ($delalaId, $amountSantim, $payoutRef) {
            $systemChapa = $this->getSystemAccount('system_chapa_holding', 'System Chapa Holding', 'asset');

            $delalaAvailable = LedgerAccount::firstOrCreate(
                ['code' => 'delala_available_wallet_'.$delalaId, 'user_id' => $delalaId],
                ['name' => 'Available Wallet - '.$delalaId, 'type' => 'liability']
            );

            // Need to verify balance in a real system, assume verified before this call
            $txRef = 'WDL_'.Str::random(10);

            $this->createEntry($txRef, $delalaAvailable, 'debit', $amountSantim, 'Withdrawal - '.$payoutRef);
            $this->createEntry($txRef, $systemChapa, 'credit', $amountSantim, 'Withdrawal - '.$payoutRef);
        });
    }

    private function createEntry(string $ref, LedgerAccount $account, string $direction, int $amount, string $description, ?int $escrowId = null): void
    {
        LedgerEntry::create([
            'transaction_ref' => $ref,
            'ledger_account_id' => $account->id,
            'escrow_transaction_id' => $escrowId,
            'direction' => $direction,
            'amount_santim' => $amount,
            'description' => $description,
        ]);
    }
}
