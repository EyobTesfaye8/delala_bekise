<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EscrowTransaction;
use App\Services\ChapaService;
use App\Services\EscrowService;
use App\Services\LedgerService;
use App\States\Escrow\CancellationRequested;
use App\States\Escrow\Disputed;
use App\States\Escrow\ForceRefunded;
use App\States\Escrow\HandoverDeclared;
use App\States\Escrow\Released;
use App\States\Job\Canceled as JobCanceled;
use App\States\Job\Completed;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EscrowController extends Controller
{
    /**
     * Webhook for Chapa payment events.
     */
    public function chapaCallback(Request $request, ChapaService $chapa, EscrowService $escrow): JsonResponse
    {
        $signature = $request->header('x-chapa-signature');
        $payload = $request->getContent();

        if (! $signature || ! $chapa->verifyWebhookSignature($payload, $signature)) {
            return response()->json(['message' => 'Invalid signature'], 400);
        }

        $data = $request->json()->all();

        // Check if successful payment
        if (($data['event'] ?? '') === 'charge.success') {
            $txRef = $data['tx_ref'] ?? null;
            if ($txRef) {
                $escrow->handlePaymentSuccess($txRef, $data);
            }
        }

        return response()->json(['message' => 'Webhook processed']);
    }

    /**
     * Delala declares item is handed over.
     */
    public function declareHandover(Request $request, EscrowTransaction $escrow): JsonResponse
    {
        if ($request->user()->id !== $escrow->delala_id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if (! $escrow->status->canTransitionTo(HandoverDeclared::class)) {
            return response()->json(['message' => 'Cannot declare handover right now.'], 400);
        }

        $escrow->status->transitionTo(HandoverDeclared::class);
        $escrow->update(['handover_declared_at' => now(), 'auto_release_at' => now()->addHours(120)]);

        return response()->json(['message' => 'Handover declared. Buyer has 120 hours to review.']);
    }

    /**
     * Buyer releases the funds.
     */
    public function releaseFunds(Request $request, EscrowTransaction $escrow, LedgerService $ledger): JsonResponse
    {
        if ($request->user()->id !== $escrow->buyer_id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if (! $escrow->status->canTransitionTo(Released::class)) {
            return response()->json(['message' => 'Cannot release funds right now.'], 400);
        }

        $escrow->status->transitionTo(Released::class);

        $ledger->recordEscrowReleased($escrow);

        $escrow->job->status->transitionTo(Completed::class);

        return response()->json(['message' => 'Funds released successfully.']);
    }

    /**
     * Buyer disputes the handover (FR-5.2).
     */
    public function dispute(Request $request, EscrowTransaction $escrow): JsonResponse
    {
        if ($request->user()->id !== $escrow->buyer_id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if (! $escrow->status->canTransitionTo(Disputed::class)) {
            return response()->json(['message' => 'Cannot dispute right now.'], 400);
        }

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:2000'],
            'reason_code' => ['required', 'in:ITEM_NOT_AS_DESCRIBED,SELLER_NO_SHOW,DEAL_FELL_THROUGH'],
            'evidence_keys' => ['nullable', 'array', 'max:5'],
            'evidence_keys.*' => ['string'],
        ]);

        $escrow->status->transitionTo(Disputed::class);

        $escrow->update([
            'dispute_reason_code' => $validated['reason_code'],
            'admin_resolution_note' => 'Dispute opened by buyer: '.$validated['reason'],
            'dispute_evidence_s3_keys' => $validated['evidence_keys'] ?? null,
        ]);

        return response()->json([
            'message' => 'Dispute opened. Delala has 48 hours to upload seller transfer evidence.',
            'escrow' => $escrow->fresh(),
        ]);
    }

    /**
     * Buyer requests cancellation before handover (SRS §4.3 & §7.5).
     */
    public function cancelRequest(Request $request, EscrowTransaction $escrow): JsonResponse
    {
        if ($request->user()->id !== $escrow->buyer_id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if (! $escrow->status->canTransitionTo(CancellationRequested::class)) {
            return response()->json(['message' => 'Cannot request cancellation in current state.'], 400);
        }

        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $escrow->status->transitionTo(CancellationRequested::class);
        $escrow->update([
            'cancellation_requested_at' => now(),
            'cancellation_deadline_at' => now()->addHours(48),
            'admin_resolution_note' => $validated['reason'] ? 'Buyer cancellation request: '.$validated['reason'] : null,
        ]);

        return response()->json([
            'message' => 'Cancellation requested. Delala has 48 hours to respond.',
            'escrow' => $escrow->fresh(),
        ]);
    }

    /**
     * Delala approves cancellation request, triggering immediate refund.
     */
    public function cancelApprove(Request $request, EscrowTransaction $escrow, ChapaService $chapa, LedgerService $ledger): JsonResponse
    {
        if ($request->user()->id !== $escrow->delala_id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if (! $escrow->status->canTransitionTo(ForceRefunded::class)) {
            return response()->json(['message' => 'Cannot approve cancellation in current state.'], 400);
        }

        DB::transaction(function () use ($escrow, $chapa, $ledger, $request) {
            $escrow->status->transitionTo(ForceRefunded::class);
            $escrow->update([
                'resolved_at' => now(),
                'resolved_by' => $request->user()->id,
                'admin_resolution_note' => 'Buyer cancellation approved by Delala',
            ]);

            // Chapa gateway refund
            $chapa->refund($escrow);

            // Double-entry ledger reversal (JNL-3)
            $ledger->recordRefund($escrow, 'Buyer cancellation approved by Delala');

            // Transition Job to Canceled
            if ($escrow->job->status->canTransitionTo(JobCanceled::class)) {
                $escrow->job->status->transitionTo(JobCanceled::class);
            }
        });

        return response()->json([
            'message' => 'Cancellation approved. Escrow refunded and job canceled.',
            'escrow' => $escrow->fresh(),
        ]);
    }

    /**
     * Delala rejects cancellation request, transitioning to Disputed.
     */
    public function cancelReject(Request $request, EscrowTransaction $escrow): JsonResponse
    {
        if ($request->user()->id !== $escrow->delala_id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if (! $escrow->status->canTransitionTo(Disputed::class)) {
            return response()->json(['message' => 'Cannot reject cancellation in current state.'], 400);
        }

        $validated = $request->validate([
            'rejection_reason' => ['required', 'string', 'max:1000'],
        ]);

        $escrow->status->transitionTo(Disputed::class);
        $escrow->update([
            'dispute_reason_code' => 'CANCELLATION_REJECTED',
            'admin_resolution_note' => 'Delala rejected cancellation: '.$validated['rejection_reason'],
        ]);

        return response()->json([
            'message' => 'Cancellation rejected. Escrow is now disputed for admin review.',
            'escrow' => $escrow->fresh(),
        ]);
    }

    /**
     * Delala uploads third-party seller receipt / bill of sale evidence (FR-5.3).
     */
    public function uploadDisputeEvidence(Request $request, EscrowTransaction $escrow): JsonResponse
    {
        if ($request->user()->id !== $escrow->delala_id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if ($escrow->status->getValue() !== 'disputed') {
            return response()->json(['message' => 'Can only upload evidence for disputed escrows.'], 400);
        }

        $validated = $request->validate([
            'evidence_keys' => ['required', 'array', 'min:1', 'max:5'],
            'evidence_keys.*' => ['string'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $existingKeys = $escrow->dispute_evidence_s3_keys ?? [];
        $mergedKeys = array_values(array_unique(array_merge($existingKeys, $validated['evidence_keys'])));

        $escrow->update([
            'dispute_evidence_s3_keys' => $mergedKeys,
            'dispute_evidence_s3_key' => $mergedKeys[0] ?? null,
            'admin_resolution_note' => $validated['notes']
                ? ($escrow->admin_resolution_note."\nDelala note: ".$validated['notes'])
                : $escrow->admin_resolution_note,
        ]);

        return response()->json([
            'message' => 'Dispute evidence uploaded successfully. Admin will review proof of transfer between Buyer and Third-Party Seller.',
            'dispute_evidence_s3_keys' => $mergedKeys,
        ]);
    }
}
