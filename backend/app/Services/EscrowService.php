<?php

namespace App\Services;

use App\Models\DelalaJob;
use App\Models\EscrowTransaction;
use App\Models\Proposal;
use App\Models\ProposalItem;
use App\States\Escrow\ForceRefunded;
use App\States\Escrow\Funded;
use App\States\Escrow\PendingPayment;
use App\States\Escrow\RefundFailed;
use App\States\Job\Contracted;
use App\States\Proposal\Accepted;
use App\States\Proposal\Rejected;
use App\States\ProposalItem\AcceptedForEscrow;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class EscrowService
{
    public function __construct(
        private ChapaService $chapaService,
        private LedgerService $ledgerService
    ) {}

    /**
     * Called when a Buyer accepts a ProposalItem.
     */
    public function initiateEscrow(ProposalItem $item, int $buyerId): ?string
    {
        return DB::transaction(function () use ($item, $buyerId) {
            $item = ProposalItem::where('id', $item->id)->lockForUpdate()->firstOrFail();
            $job = $item->proposal->job;

            if ($job->status->getValue() === 'contracted' || $job->status->getValue() === 'completed') {
                throw new RuntimeException('Job is already contracted.');
            }

            // Create Escrow Record
            $commissionSantim = $item->commission_amount_santim;
            $platformFeeSantim = (int) ($commissionSantim * 0.05); // 5%
            $delalaAmountSantim = $commissionSantim - $platformFeeSantim; // 95%

            $escrow = EscrowTransaction::create([
                'uuid' => (string) Str::uuid(),
                'job_id' => $job->id,
                'proposal_item_id' => $item->id,
                'buyer_id' => $buyerId,
                'delala_id' => $item->proposal->delala_id,
                'commission_amount_santim' => $commissionSantim,
                'platform_fee_santim' => $platformFeeSantim,
                'delala_amount_santim' => $delalaAmountSantim,
            ]); // Status is pending_payment by default via State

            // Get Chapa URL
            $url = $this->chapaService->generateCheckoutUrl($escrow);

            if (! $url) {
                // Do not leave orphaned records. Rollback happens automatically
                // if we throw, or we just fail the transaction.
                throw new RuntimeException('Chapa API unavailable.');
            }

            return $url;
        });
    }

    /**
     * Handles Chapa Webhook "payment.success"
     */
    public function handlePaymentSuccess(string $txRef, array $payload): void
    {
        DB::transaction(function () use ($txRef, $payload) {
            $escrow = EscrowTransaction::where('chapa_tx_ref', $txRef)->lockForUpdate()->first();

            if (! $escrow) {
                return; // Not our transaction or invalid
            }

            // Idempotency check
            if (! $escrow->status->equals(PendingPayment::class)) {
                return;
            }

            $job = $escrow->job;
            $job = DelalaJob::where('id', $job->id)->lockForUpdate()->firstOrFail();

            // First-to-Fund Wins concurrency check
            if ($job->status->getValue() === 'contracted') {
                // Secondary buyer race condition! Job already awarded.
                // We must refund this secondary buyer immediately.
                $refundSuccess = $this->chapaService->refund($escrow);

                $escrow->update(['chapa_webhook_payload' => $payload]);
                $escrow->status->transitionTo($refundSuccess ? ForceRefunded::class : RefundFailed::class);

                // @todo: Notify buyer "Job already awarded. Refund issued."
                return;
            }

            // WE WON THE RACE!
            // 1. Transition Job to Contracted
            $job->status->transitionTo(Contracted::class);

            // 2. Transition Proposal and Item
            $proposal = $escrow->proposalItem->proposal;
            $proposal->status->transitionTo(Accepted::class);
            $escrow->proposalItem->status->transitionTo(AcceptedForEscrow::class);

            // 3. Mark all other proposals for this job as Rejected (Implicitly)
            Proposal::where('job_id', $job->id)
                ->where('id', '!=', $proposal->id)
                ->get()
                ->each(function ($otherProposal) {
                    if ($otherProposal->status->canTransitionTo(Rejected::class)) {
                        $otherProposal->status->transitionTo(Rejected::class);

                        // Set their chat to read-only
                        if ($otherProposal->chat) {
                            $otherProposal->chat->update(['is_read_only' => true]);
                        }
                    }
                });

            // Set THIS proposal's chat to read-only too (negotiation is over)
            if ($proposal->chat) {
                $proposal->chat->update(['is_read_only' => true]);
            }

            // 4. Update Escrow State and write Ledger Entries
            $escrow->update([
                'chapa_paid_at' => now(),
                'chapa_webhook_payload' => $payload,
            ]);
            $escrow->status->transitionTo(Funded::class);

            $this->ledgerService->recordEscrowFunded($escrow);

            // @todo: Notify Delala "Buyer funded [Amount]. Begin work."
        });
    }

    // Additional methods for cancel/release/dispute would follow the same pattern...
}
