<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DelalaJob;
use App\Models\Proposal;
use App\Notifications\NewProposalNotification;
use App\Services\EscrowService;
use App\States\Proposal\Rejected;
use App\States\Proposal\Withdrawn;
use App\States\ProposalItem\Pending;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProposalController extends Controller
{
    /**
     * Submit a multi-item proposal (Delala only).
     */
    public function store(Request $request, DelalaJob $job): JsonResponse
    {
        $user = $request->user();

        // Must be Delala, must be KYC Approved
        if (! $user->isDelala() || $user->kycProfile?->status->getValue() !== 'approved') {
            return response()->json(['message' => 'Unauthorized or KYC not approved.'], 403);
        }

        // Job must be accepting proposals
        if (! in_array($job->status->getValue(), ['open', 'negotiating'])) {
            return response()->json(['message' => 'Job is not accepting proposals.'], 400);
        }

        // Validate payload
        $validated = $request->validate([
            'cover_note' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.item_name' => 'required|string|max:255',
            'items.*.description' => 'nullable|string',
            'items.*.commission_amount_santim' => 'required|integer|min:5000', // min 50 ETB
            'items.*.image_s3_keys' => 'nullable|array|max:5',
            'items.*.image_s3_keys.*' => 'string',
        ]);

        $proposal = DB::transaction(function () use ($user, $job, $validated) {
            $proposal = Proposal::create([
                'job_id' => $job->id,
                'delala_id' => $user->id,
                'cover_note' => $validated['cover_note'] ?? null,
            ]);

            foreach ($validated['items'] as $itemData) {
                $proposal->items()->create([
                    'item_name' => $itemData['item_name'],
                    'description' => $itemData['description'] ?? null,
                    'commission_amount_santim' => $itemData['commission_amount_santim'],
                    'image_s3_keys' => $itemData['image_s3_keys'] ?? null,
                ]); // status is pending by default
            }

            $job->increment('proposal_count');

            return $proposal;
        });

        // notify buyer "New proposal from [Delala]"
        $job->buyer->notify(new NewProposalNotification($proposal));

        return response()->json([
            'message' => 'Proposal submitted successfully',
            'proposal' => $proposal->load('items'),
        ], 201);
    }

    /**
     * Withdraw a proposal (Delala only, if pending).
     */
    public function withdraw(Request $request, Proposal $proposal): JsonResponse
    {
        if ($request->user()->id !== $proposal->delala_id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if (! $proposal->status->canTransitionTo(Withdrawn::class)) {
            return response()->json(['message' => 'Cannot withdraw proposal in its current state.'], 400);
        }

        $proposal->status->transitionTo(Withdrawn::class);

        return response()->json(['message' => 'Proposal withdrawn']);
    }

    /**
     * Reject a proposal (Buyer only).
     */
    public function reject(Request $request, Proposal $proposal): JsonResponse
    {
        if ($request->user()->id !== $proposal->job->buyer_id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if (! $proposal->status->canTransitionTo(Rejected::class)) {
            return response()->json(['message' => 'Cannot reject proposal in its current state.'], 400);
        }

        $proposal->status->transitionTo(Rejected::class);

        if ($proposal->chat) {
            $proposal->chat->update(['is_read_only' => true]);
        }

        return response()->json(['message' => 'Proposal rejected']);
    }

    /**
     * Accept a single item in a proposal (Buyer only).
     * Transitions Escrow to PendingPayment and returns Checkout URL.
     */
    public function acceptItem(Request $request, Proposal $proposal, $itemId, EscrowService $escrowService): JsonResponse
    {
        if ($request->user()->id !== $proposal->job->buyer_id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $item = $proposal->items()->findOrFail($itemId);

        try {
            $checkoutUrl = $escrowService->initiateEscrow($item, $request->user()->id);

            return response()->json([
                'message' => 'Item accepted, please proceed to payment.',
                'checkout_url' => $checkoutUrl,
            ]);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }
}
