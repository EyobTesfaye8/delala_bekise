<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Escrow\DisputeRequest;
use App\Http\Requests\Escrow\InitiateEscrowRequest;
use App\Http\Resources\EscrowResource;
use App\Models\EscrowTransaction;
use App\Models\ProposalItem;
use App\Services\ChapaService;
use App\Services\EscrowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Escrow lifecycle controller.
 * SRS §4.3, §5.2, §9.2.
 */
class EscrowController extends Controller
{
    public function __construct(
        private readonly EscrowService $escrowService,
        private readonly ChapaService  $chapaService,
    ) {}

    /** POST /api/v1/escrow/initiate */
    public function initiate(InitiateEscrowRequest $request): JsonResponse
    {
        $item = ProposalItem::where('uuid', $request->input('proposal_item_uuid'))->firstOrFail();

        // Only the job's buyer may initiate escrow
        if ($item->proposal->job->buyer_id !== $request->user()->id) {
            return response()->json(['error' => 'FORBIDDEN'], 403);
        }

        try {
            $result = $this->escrowService->initiate($item, $request->user(), $request);
        } catch (\Exception $e) {
            $statusCode = match ((int) $e->getCode()) {
                409     => 409,
                422     => 422,
                503     => 503,
                default => 422,
            };

            return response()->json(['error' => $e->getMessage()], $statusCode);
        }

        return response()->json([
            'message'      => 'Escrow initiated. Redirect buyer to checkout_url to complete payment.',
            'checkout_url' => $result['checkout_url'],
            'escrow'       => new EscrowResource($result['escrow']),
        ], 201);
    }

    /**
     * POST /api/v1/escrow/webhook — Chapa payment webhook.
     * SRS NFR-SEC-4: HMAC SHA-256 verification required.
     * This route is NOT behind auth:sanctum.
     */
    public function webhook(Request $request): JsonResponse
    {
        $rawPayload       = $request->getContent();
        $receivedSignature = $request->header('Chapa-Signature', '');

        if (! $this->chapaService->verifyWebhookSignature($rawPayload, $receivedSignature)) {
            return response()->json(['error' => 'INVALID_SIGNATURE'], 401);
        }

        $payload = $request->all();

        if (($payload['status'] ?? '') !== 'success') {
            // Not a successful payment — acknowledge but take no action
            return response()->json(['message' => 'acknowledged']);
        }

        try {
            $this->escrowService->processWebhook($payload, $request);
        } catch (\Throwable $e) {
            // Always return 200 to Chapa to prevent retries flooding broken state
            return response()->json(['message' => 'acknowledged', 'warning' => $e->getMessage()], 200);
        }

        return response()->json(['message' => 'acknowledged']);
    }

    /** GET /api/v1/escrow/{escrow} */
    public function show(Request $request, EscrowTransaction $escrow): JsonResponse
    {
        $user = $request->user();

        if ($user->id !== $escrow->buyer_id && $user->id !== $escrow->delala_id && ! $user->isAdmin()) {
            return response()->json(['error' => 'FORBIDDEN'], 403);
        }

        return response()->json(['escrow' => new EscrowResource($escrow)]);
    }

    /** POST /api/v1/escrow/{escrow}/request-cancellation */
    public function requestCancellation(Request $request, EscrowTransaction $escrow): JsonResponse
    {
        if ($request->user()->id !== $escrow->buyer_id) {
            return response()->json(['error' => 'FORBIDDEN'], 403);
        }

        try {
            $this->escrowService->requestCancellation($escrow, $request);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json(['message' => 'Cancellation request submitted. Delala has 24 hours to respond.']);
    }

    /** POST /api/v1/escrow/{escrow}/respond-cancellation */
    public function respondCancellation(Request $request, EscrowTransaction $escrow): JsonResponse
    {
        if ($request->user()->id !== $escrow->delala_id) {
            return response()->json(['error' => 'FORBIDDEN'], 403);
        }

        $request->validate(['accept' => 'required|boolean']);

        try {
            $this->escrowService->respondToCancellation($escrow, (bool) $request->input('accept'), $request);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        $msg = $request->boolean('accept')
            ? 'Cancellation accepted. Refund initiated.'
            : 'Cancellation rejected. Dispute opened.';

        return response()->json(['message' => $msg, 'escrow' => new EscrowResource($escrow->fresh())]);
    }

    /** POST /api/v1/escrow/{escrow}/declare-handover */
    public function declareHandover(Request $request, EscrowTransaction $escrow): JsonResponse
    {
        if ($request->user()->id !== $escrow->delala_id) {
            return response()->json(['error' => 'FORBIDDEN'], 403);
        }

        try {
            $this->escrowService->declareHandover($escrow, $request);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Handover declared. Buyer has 48 hours to confirm or dispute.',
            'escrow'  => new EscrowResource($escrow->fresh()),
        ]);
    }

    /** POST /api/v1/escrow/{escrow}/accept-handover */
    public function acceptHandover(Request $request, EscrowTransaction $escrow): JsonResponse
    {
        if ($request->user()->id !== $escrow->buyer_id) {
            return response()->json(['error' => 'FORBIDDEN'], 403);
        }

        try {
            $this->escrowService->acceptHandover($escrow, $request);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Handover accepted. Commission released to Delala.',
            'escrow'  => new EscrowResource($escrow->fresh()),
        ]);
    }

    /** POST /api/v1/escrow/{escrow}/dispute */
    public function dispute(DisputeRequest $request, EscrowTransaction $escrow): JsonResponse
    {
        if ($request->user()->id !== $escrow->buyer_id) {
            return response()->json(['error' => 'FORBIDDEN'], 403);
        }

        try {
            $this->escrowService->openDispute($escrow, $request->input('reason_code'), $request);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Dispute opened. Admin will review within 2 business days.',
            'escrow'  => new EscrowResource($escrow->fresh()),
        ]);
    }

    /** POST /api/v1/escrow/{escrow}/upload-evidence */
    public function uploadEvidence(Request $request, EscrowTransaction $escrow): JsonResponse
    {
        $user = $request->user();
        if ($user->id !== $escrow->buyer_id && $user->id !== $escrow->delala_id) {
            return response()->json(['error' => 'FORBIDDEN'], 403);
        }

        if (! $escrow->isDisputed()) {
            return response()->json(['error' => 'ESCROW_NOT_IN_DISPUTE'], 422);
        }

        $request->validate(['evidence_s3_keys' => 'required|array|min:1|max:5', 'evidence_s3_keys.*' => 'string|max:500']);

        $existing = $escrow->dispute_evidence_s3_keys ?? [];
        $updated  = array_merge($existing, $request->input('evidence_s3_keys'));

        $escrow->update(['dispute_evidence_s3_keys' => array_slice($updated, 0, 10)]);

        return response()->json(['message' => 'Evidence uploaded.', 'evidence_count' => count($updated)]);
    }
}
