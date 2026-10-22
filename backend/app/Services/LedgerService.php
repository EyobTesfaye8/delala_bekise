<?php

namespace App\Services;

use App\Models\LedgerAccount;
use App\Models\LedgerEntry;
use App\Models\EscrowTransaction;
use App\Services\AlertService;
use Brick\Money\Money;
use Brick\Money\Currency;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Double-entry ledger service.
 * SRS §5.1 – §5.2: All monetary values in Santim (BIGINT). No floats. brick/money.
 *
 * Platform fee: 5% of gross commission (SRS §1.2).
 * Chapa fee: ~2% absorbed by platform (SRS §5.2 JNL-1A).
 */
class LedgerService
{
    private const PLATFORM_FEE_RATE   = 0.05;  // 5%
    private const CHAPA_FEE_RATE      = 0.02;  // 2% (approx)

    public function __construct(private readonly AlertService $alert) {}

    /**
     * JNL-1A + JNL-1B: Record funded escrow and split commission.
     * Triggered by verified Chapa webhook (SRS §5.2).
     *
     * @param  int  $grossSantim   Full commission paid by Buyer
     * @param  int  $netSettledSantim  Amount actually settled by Chapa (after 2% fee)
     */
    public function recordFunding(EscrowTransaction $escrow, int $grossSantim, int $netSettledSantim): void
    {
        DB::transaction(function () use ($escrow, $grossSantim, $netSettledSantim) {
            $txRef           = 'JNL-'.Str::upper(Str::random(10));
            $chapaFeeSantim  = $grossSantim - $netSettledSantim;
            $platformRevenue = (int) round($grossSantim * self::PLATFORM_FEE_RATE);
            $delalaAmount    = $grossSantim - $platformRevenue;

            // JNL-1A: Record actual cash received + Chapa fee expense + Buyer liability
            $this->debit(
                code: LedgerAccount::SYSTEM_CHAPA_HOLDING,
                amount: $netSettledSantim,
                ref: $txRef,
                escrow: $escrow,
                description: 'Chapa net settlement for escrow '.$escrow->uuid,
            );
            $this->debit(
                code: LedgerAccount::CHAPA_GATEWAY_EXPENSE,
                amount: $chapaFeeSantim,
                ref: $txRef,
                escrow: $escrow,
                description: 'Chapa ~2% gateway fee absorbed by platform',
            );
            $this->credit(
                code: LedgerAccount::BUYER_ESCROW_LIABILITY,
                amount: $grossSantim,
                ref: $txRef,
                escrow: $escrow,
                description: 'Buyer escrow liability — full gross commission',
            );

            // JNL-1B: Provisional split (atomic with 1A)
            $this->debit(
                code: LedgerAccount::BUYER_ESCROW_LIABILITY,
                amount: $grossSantim,
                ref: $txRef,
                escrow: $escrow,
                description: 'Clear buyer escrow liability via provisional split',
            );
            $this->credit(
                code: LedgerAccount::DELALA_PENDING_WALLET,
                amount: $delalaAmount,
                ref: $txRef,
                escrow: $escrow,
                userId: $escrow->delala_id,
                description: '95% allocated to Delala pending wallet',
            );
            $this->credit(
                code: LedgerAccount::PLATFORM_REVENUE_WALLET,
                amount: $platformRevenue,
                ref: $txRef,
                escrow: $escrow,
                description: '5% platform fee credited to revenue',
            );

            $this->verifyBalance($txRef);

            // Persist fee amounts on the escrow record
            $escrow->update([
                'platform_fee_santim' => $platformRevenue,
                'delala_amount_santim' => $delalaAmount,
            ]);
        });
    }

    /**
     * JNL-2: Release escrow — move Delala funds to available wallet.
     */
    public function recordRelease(EscrowTransaction $escrow): void
    {
        DB::transaction(function () use ($escrow) {
            $txRef = 'JNL-'.Str::upper(Str::random(10));

            $this->debit(
                code: LedgerAccount::DELALA_PENDING_WALLET,
                amount: $escrow->delala_amount_santim,
                ref: $txRef,
                escrow: $escrow,
                userId: $escrow->delala_id,
                description: 'Escrow released — move from pending to available',
            );
            $this->credit(
                code: LedgerAccount::DELALA_AVAILABLE_WALLET,
                amount: $escrow->delala_amount_santim,
                ref: $txRef,
                escrow: $escrow,
                userId: $escrow->delala_id,
                description: 'Delala commission now available for withdrawal',
            );

            $this->verifyBalance($txRef);
        });
    }

    /**
     * JNL-3: Refund escrow — reverse provisional split (AUTO_REFUNDED / FORCE_REFUNDED).
     * NOTE: Do NOT call until Chapa refund API call succeeds.
     */
    public function recordRefund(EscrowTransaction $escrow): void
    {
        DB::transaction(function () use ($escrow) {
            $txRef = 'JNL-'.Str::upper(Str::random(10));

            $this->debit(
                code: LedgerAccount::DELALA_PENDING_WALLET,
                amount: $escrow->delala_amount_santim,
                ref: $txRef,
                escrow: $escrow,
                userId: $escrow->delala_id,
                description: 'Refund — reverse Delala pending allocation',
            );
            $this->debit(
                code: LedgerAccount::PLATFORM_REVENUE_WALLET,
                amount: $escrow->platform_fee_santim,
                ref: $txRef,
                escrow: $escrow,
                description: 'Refund — reverse platform revenue (Chapa fee stays as expense loss)',
            );
            $this->credit(
                code: LedgerAccount::SYSTEM_CHAPA_HOLDING,
                amount: $escrow->commission_amount_santim,
                ref: $txRef,
                escrow: $escrow,
                description: 'Full gross commission returned to Buyer via Chapa refund',
            );

            $this->verifyBalance($txRef);
        });
    }

    /**
     * JNL-4A: Delala requests withdrawal — lock funds in payout clearing.
     */
    public function recordWithdrawalRequest(int $delalaId, int $amountSantim, string $payoutRef): void
    {
        DB::transaction(function () use ($delalaId, $amountSantim, $payoutRef) {
            $txRef = 'JNL-'.$payoutRef;

            $this->debit(
                code: LedgerAccount::DELALA_AVAILABLE_WALLET,
                amount: $amountSantim,
                ref: $txRef,
                userId: $delalaId,
                description: 'Withdrawal requested — deduct from available wallet',
            );
            $this->credit(
                code: LedgerAccount::SYSTEM_PAYOUT_CLEARING,
                amount: $amountSantim,
                ref: $txRef,
                description: 'In-transit payout clearing',
            );

            $this->verifyBalance($txRef);
        });
    }

    /**
     * JNL-4B: Admin marks payout PAID — clear payout clearing account.
     */
    public function recordPayoutPaid(int $amountSantim, string $payoutRef): void
    {
        DB::transaction(function () use ($amountSantim, $payoutRef) {
            $txRef = 'JNL-PAID-'.$payoutRef;

            $this->debit(
                code: LedgerAccount::SYSTEM_PAYOUT_CLEARING,
                amount: $amountSantim,
                ref: $txRef,
                description: 'Payout cleared — funds left merchant account',
            );
            $this->credit(
                code: LedgerAccount::SYSTEM_CHAPA_HOLDING,
                amount: $amountSantim,
                ref: $txRef,
                description: 'Cash exits Chapa merchant account for Delala payout',
            );

            $this->verifyBalance($txRef);
        });
    }

    // ─────────────────────────── Balance Verification ──────────────────────

    /**
     * Verify that debits == credits for a transaction reference.
     * SRS FIN-1.2: Fire P1 alert on any imbalance.
     */
    private function verifyBalance(string $txRef): void
    {
        $entries = LedgerEntry::where('transaction_ref', $txRef)->get();
        $debits  = $entries->where('direction', 'debit')->sum('amount_santim');
        $credits = $entries->where('direction', 'credit')->sum('amount_santim');

        if ($debits !== $credits) {
            $this->alert->p1Critical('LEDGER IMBALANCE DETECTED', [
                'transaction_ref' => $txRef,
                'debits_santim'   => $debits,
                'credits_santim'  => $credits,
                'delta'           => $debits - $credits,
            ]);

            throw new \RuntimeException("Ledger imbalance on {$txRef}: Dr={$debits} Cr={$credits}");
        }
    }

    // ─────────────────────────── Private Helpers ───────────────────────────

    private function debit(
        string $code,
        int $amount,
        string $ref,
        ?EscrowTransaction $escrow = null,
        ?int $userId = null,
        string $description = '',
    ): void {
        $account = $this->getOrCreateAccount($code, $userId);
        LedgerEntry::create([
            'transaction_ref'       => $ref,
            'ledger_account_id'     => $account->id,
            'escrow_transaction_id' => $escrow?->id,
            'direction'             => 'debit',
            'amount_santim'         => $amount,
            'description'           => $description,
        ]);
    }

    private function credit(
        string $code,
        int $amount,
        string $ref,
        ?EscrowTransaction $escrow = null,
        ?int $userId = null,
        string $description = '',
    ): void {
        $account = $this->getOrCreateAccount($code, $userId);
        LedgerEntry::create([
            'transaction_ref'       => $ref,
            'ledger_account_id'     => $account->id,
            'escrow_transaction_id' => $escrow?->id,
            'direction'             => 'credit',
            'amount_santim'         => $amount,
            'description'           => $description,
        ]);
    }

    private function getOrCreateAccount(string $code, ?int $userId = null): LedgerAccount
    {
        $names = [
            LedgerAccount::SYSTEM_CHAPA_HOLDING    => ['system_chapa_holding', 'asset'],
            LedgerAccount::SYSTEM_PAYOUT_CLEARING  => ['system_payout_clearing', 'asset'],
            LedgerAccount::BUYER_ESCROW_LIABILITY  => ['buyer_escrow_liability', 'liability'],
            LedgerAccount::DELALA_PENDING_WALLET   => ['delala_pending_wallet', 'liability'],
            LedgerAccount::DELALA_AVAILABLE_WALLET => ['delala_available_wallet', 'liability'],
            LedgerAccount::PLATFORM_REVENUE_WALLET => ['platform_revenue_wallet', 'equity'],
            LedgerAccount::CHAPA_GATEWAY_EXPENSE   => ['chapa_gateway_expense', 'expense'],
        ];

        [$name, $type] = $names[$code];

        return LedgerAccount::firstOrCreate(
            ['code' => $code, 'user_id' => $userId],
            ['name' => $name, 'type' => $type],
        );
    }
}
