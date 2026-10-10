<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EscrowTransaction;
use App\Services\ChapaService;
use App\Services\EscrowService;
use App\Services\LedgerService;
use App\States\Escrow\Disputed;
use App\States\Escrow\HandoverDeclared;
use App\States\Escrow\Released;
use App\States\Job\Completed;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
     * Buyer disputes the handover.
     */
    public function dispute(Request $request, EscrowTransaction $escrow): JsonResponse
    {
        if ($request->user()->id !== $escrow->buyer_id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if (! $escrow->status->canTransitionTo(Disputed::class)) {
            return response()->json(['message' => 'Cannot dispute right now.'], 400);
        }

        $request->validate([
            'reason' => 'required|string',
        ]);

        $escrow->status->transitionTo(Disputed::class);

        // Save evidence or note (omitted complex S3 upload for brevity in controller)
        $escrow->update(['admin_resolution_note' => 'Dispute reason: '.$request->reason]);

        return response()->json(['message' => 'Dispute opened. An admin will review.']);
    }
}
