<?php

namespace App\Services;

use App\Models\EscrowTransaction;
use App\Models\ProposalItem;
use App\Models\User;
use App\Models\States\EscrowFunded;
use App\Models\States\EscrowHandoverDeclared;
use App\Models\States\EscrowCancellationRequested;
use App\Models\States\EscrowAutoRefunded;
use App\Models\States\EscrowDisputed;
use App\Models\States\EscrowReleased;
use App\Models\States\EscrowRefundFailed;
use App\Services\AlertService;
use App\Services\AuditLogService;
use App\Services\ChapaService;
use App\Services\LedgerService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Escrow lifecycle service.
 * SRS §4.3, §5.2, §9.2.
 *
 * Key invariants:
 *  - ALL ledger writes inside DB::transaction() with pessimistic row-locking
 *  - Chapa API calls NEVER inside DB::transaction() — prepare ref, then call, then persist
 *  - REFUND_FAILED is a terminal-pending state; retried by ReconcileRefundFailedJob
 */
class EscrowService
{
    /** Auto-release window: 48 hrs after handover declared (SRS §4.3) */
    private const AUTO_RELEASE_HOURS = 48;

    /** Cancellation response window: 24 hrs (SRS §4.3) */
    private const CANCELLATION_WINDOW_HOURS = 24;

    public function __construct(
        private readonly ChapaService  $chapa,
        private readonly LedgerService $ledger,
        private readonly AuditLogService $auditLog,
        private readonly AlertService  $alert,
    ) {}

    /**
     * Initiate escrow checkout session for a proposal item.
     * SRS FR-5.1.
     *
     * @return array{escrow: EscrowTransaction, checkout_url: string}
     */
    public function initiate(ProposalItem $item, User $buyer, Request $request): array
    {
        // Use pessimistic lock on the proposal item to prevent double-escrow
        DB::transaction(function () use ($item) {
            ProposalItem::where('id', $item->id)->lockForUpdate()->first();
        });

        if (
            EscrowTransaction::where('proposal_item_id', $item->id)
                ->whereIn('status', ['funded', 'handover_declared', 'disputed'])
                ->exists()
        ) {
            throw new \Exception('ESCROW_ALREADY_ACTIVE', 409);
        }

        // Validate the accepted proposal has commission set
        if (! $item->commission_amount_santim) {
            throw new \Exception('COMMISSION_NOT_SET', 422);
        }

        // Create pending escrow record (before calling Chapa)
        $escrow = EscrowTransaction::create([
            'job_id'                   => $item->proposal->job_id,
            'proposal_item_id'         => $item->id,
            'buyer_id'                 => $buyer->id,
            'delala_id'                => $item->proposal->delala_id,
            'commission_amount_santim' => $item->commission_amount_santim,
        ]);

        // Call Chapa OUTSIDE any DB transaction
        $callbackUrl = route('api.v1.escrow.webhook');
        $returnUrl   = config('app.frontend_url', 'http://localhost').'/escrow/'.$escrow->uuid.'/callback';

        try {
            $payment = $this->chapa->initializePayment(
                amountSantim: $item->commission_amount_santim,
                buyerEmail:   $buyer->email,
                buyerName:    $buyer->full_name,
                buyerPhone:   $buyer->phone,
                returnUrl:    $returnUrl,
                callbackUrl:  $callbackUrl,
                metadata:     ['escrow_uuid' => $escrow->uuid],
            );
        } catch (\Exception $e) {
            // Chapa unavailable — delete the pending escrow and surface 503
            $escrow->delete();
            throw $e;
        }

        $escrow->update([
            'chapa_tx_ref'        => $payment['tx_ref'],
            'chapa_checkout_url'  => $payment['checkout_url'],
        ]);

        $this->auditLog->log('escrow_initiated', EscrowTransaction::class, $escrow->id, [], [
            'chapa_tx_ref' => $payment['tx_ref'],
        ], $request);

        return ['escrow' => $escrow->fresh(), 'checkout_url' => $payment['checkout_url']];
    }

    /**
     * Process verified Chapa webhook.
     * SRS §5.2: First-to-Fund-Wins via SELECT FOR UPDATE on escrow row.
     * Idempotent — safe to call multiple times for the same tx_ref.
     */
    public function processWebhook(array $payload, Request $request): void
    {
        $txRef = $payload['tx_ref'] ?? null;
        if (! $txRef) {
            return;
        }

        $escrow = EscrowTransaction::where('chapa_tx_ref', $txRef)->firstOrFail();

        // Idempotency: already funded → ignore duplicate webhook
        if ($escrow->isFunded() || $escrow->isReleased() || $escrow->isRefunded()) {
            Log::info('Duplicate Chapa webhook ignored', ['tx_ref' => $txRef]);

            return;
        }

        // Pessimistic lock — SRS "First-to-Fund Wins"
        DB::transaction(function () use ($escrow, $payload, $txRef, $request) {
            $locked = EscrowTransaction::where('id', $escrow->id)->lockForUpdate()->first();

            if (! $locked->isPendingPayment()) {
                return; // Another request won the race
            }

            // Verify payment amount matches expected
            $paidSantim       = (int) round(($payload['amount'] ?? 0) * 100);
            $expectedSantim   = $locked->commission_amount_santim;

            if ($paidSantim < $expectedSantim) {
                // Underpayment — keep pending, alert
                $this->alert->p2High('Chapa underpayment detected', [
                    'tx_ref'   => $txRef,
                    'paid'     => $paidSantim,
                    'expected' => $expectedSantim,
                ]);

                return;
            }

            // Handle overpayment — record ref for refund
            $overpaymentSantim = $paidSantim - $expectedSantim;
            $overpaymentRef    = $overpaymentSantim > 0 ? $txRef.'-OVERPAY' : null;

            $locked->update([
                'chapa_paid_at'       => now(),
                'chapa_webhook_payload' => $payload,
                'auto_release_at'     => null, // Set after handover
                'overpayment_tx_ref'  => $overpaymentRef,
            ]);

            $locked->status->transitionTo(EscrowFunded::class);
            $locked->save();

            // Write ledger journals
            $this->ledger->recordFunding($locked, $expectedSantim, $paidSantim);

            $this->auditLog->log('escrow_funded', EscrowTransaction::class, $locked->id, [], [
                'paid_santim' => $paidSantim,
                'tx_ref'      => $txRef,
            ], $request);
        });
    }

    /**
     * Buyer requests cancellation while escrow is FUNDED.
     * SRS §4.3: Opens 24-hr window for Delala to object.
     */
    public function requestCancellation(EscrowTransaction $escrow, Request $request): void
    {
        if (! $escrow->isFunded()) {
            throw new \Exception('INVALID_STATE_FOR_CANCELLATION', 422);
        }

        DB::transaction(function () use ($escrow, $request) {
            $escrow->status->transitionTo(EscrowCancellationRequested::class);
            $escrow->update([
                'cancellation_requested_at' => now(),
                'cancellation_deadline_at'  => now()->addHours(self::CANCELLATION_WINDOW_HOURS),
            ]);

            $this->auditLog->log('escrow_cancellation_requested', EscrowTransaction::class, $escrow->id, [], [], $request);
        });
    }

    /**
     * Delala responds to cancellation request.
     * If $accept = true → auto-refund. If false → escalate to dispute.
     * SRS §4.3.
     */
    public function respondToCancellation(EscrowTransaction $escrow, bool $accept, Request $request): void
    {
        if (! $escrow->isCancellationRequested()) {
            throw new \Exception('INVALID_STATE_FOR_CANCELLATION_RESPONSE', 422);
        }

        if ($accept) {
            $this->executeRefund($escrow, 'Delala accepted cancellation', $request);
        } else {
            DB::transaction(function () use ($escrow, $request) {
                $escrow->status->transitionTo(EscrowDisputed::class);
                $this->auditLog->log('escrow_dispute_opened', EscrowTransaction::class, $escrow->id, [], ['origin' => 'cancellation_rejected'], $request);
            });
        }
    }

    /**
     * Delala declares handover completed.
     * SRS §4.3: Sets 48-hr auto-release window.
     */
    public function declareHandover(EscrowTransaction $escrow, Request $request): void
    {
        if (! $escrow->isFunded()) {
            throw new \Exception('INVALID_STATE_FOR_HANDOVER', 422);
        }

        DB::transaction(function () use ($escrow, $request) {
            $escrow->status->transitionTo(EscrowHandoverDeclared::class);
            $escrow->update([
                'handover_declared_at' => now(),
                'auto_release_at'      => Carbon::now()->addHours(self::AUTO_RELEASE_HOURS),
            ]);

            $this->auditLog->log('escrow_handover_declared', EscrowTransaction::class, $escrow->id, [], [], $request);
        });
    }

    /**
     * Buyer accepts handover — immediate release.
     * SRS §4.3.
     */
    public function acceptHandover(EscrowTransaction $escrow, Request $request): void
    {
        if (! $escrow->isHandoverDeclared()) {
            throw new \Exception('INVALID_STATE_FOR_HANDOVER_ACCEPT', 422);
        }

        $this->executeRelease($escrow, $request);
    }

    /**
     * Buyer opens dispute.
     * SRS §4.3.
     */
    public function openDispute(EscrowTransaction $escrow, string $reasonCode, Request $request): void
    {
        if (! $escrow->isHandoverDeclared() && ! $escrow->isFunded()) {
            throw new \Exception('INVALID_STATE_FOR_DISPUTE', 422);
        }

        DB::transaction(function () use ($escrow, $reasonCode, $request) {
            $escrow->status->transitionTo(EscrowDisputed::class);
            $escrow->update(['dispute_reason_code' => $reasonCode]);

            $this->auditLog->log('escrow_dispute_opened', EscrowTransaction::class, $escrow->id, [], [
                'reason_code' => $reasonCode,
            ], $request);
        });
    }

    // ─────────────────────────── Internal helpers ──────────────────────────

    private function executeRelease(EscrowTransaction $escrow, Request $request): void
    {
        DB::transaction(function () use ($escrow, $request) {
            $locked = EscrowTransaction::where('id', $escrow->id)->lockForUpdate()->first();
            $locked->status->transitionTo(EscrowReleased::class);
            $this->ledger->recordRelease($locked);
            $this->auditLog->log('escrow_released', EscrowTransaction::class, $locked->id, [], [], $request);
        });
    }

    private function executeRefund(EscrowTransaction $escrow, string $reason, Request $request): void
    {
        try {
            // Call Chapa BEFORE writing to ledger
            $this->chapa->initiateRefund(
                $escrow->chapa_tx_ref,
                $escrow->commission_amount_santim,
                $reason,
            );
        } catch (\Exception $e) {
            // Refund failed — set terminal-pending state, alert, do NOT write ledger
            DB::transaction(function () use ($escrow, $e, $request) {
                $escrow->status->transitionTo(EscrowRefundFailed::class);
                $this->alert->p1Critical('ESCROW REFUND_FAILED', [
                    'escrow_uuid' => $escrow->uuid,
                    'error'       => $e->getMessage(),
                ]);
                $this->auditLog->log('escrow_refund_failed', EscrowTransaction::class, $escrow->id, [], [
                    'error' => $e->getMessage(),
                ], $request);
            });

            return;
        }

        // Chapa refund succeeded — now write ledger
        DB::transaction(function () use ($escrow, $request) {
            $locked = EscrowTransaction::where('id', $escrow->id)->lockForUpdate()->first();
            $locked->status->transitionTo(EscrowAutoRefunded::class);
            $this->ledger->recordRefund($locked);
            $this->auditLog->log('escrow_refunded', EscrowTransaction::class, $locked->id, [], [], $request);
        });
    }
}
