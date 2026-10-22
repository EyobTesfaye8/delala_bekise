<?php

namespace App\Http\Controllers\Api;

use App\Events\CommissionUpdated;
use App\Http\Controllers\Controller;
use App\Models\DelalaJob;
use App\Models\Proposal;
use App\Models\ProposalItem;
use App\Models\ProposalItemPriceHistory;
use App\Notifications\NewProposalNotification;
use App\Services\EscrowService;
use App\States\Proposal\Rejected;
use App\States\Proposal\Withdrawn;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProposalController extends Controller
{
    /**
     * Submit a multi-item proposal (Delala only) (FR-4.1 & FR-4.2).
     */
    public function store(Request $request, DelalaJob $job): JsonResponse
    {
        $user = $request->user();

        // Must be Delala, must be KYC Approved
        if (! $user->isDelala() || $user->kycProfile?->status->getValue() !== 'approved') {
            return response()->json(['message' => 'Unauthorized or KYC not approved.'], 403);
        }

        // Job must be accepting proposals
        if (! in_array($job->status->getValue(), ['open', 'negotiating'], true)) {
            return response()->json(['message' => 'Job is not accepting proposals.'], 400);
        }

        // SRS §4.2 Constraint 1: A Delala may only have one active proposal per Job
        $alreadyProposed = Proposal::where('job_id', $job->id)
            ->where('delala_id', $user->id)
            ->whereNotIn('status', ['withdrawn', 'rejected'])
            ->exists();

        if ($alreadyProposed) {
            return response()->json([
                'message' => 'You already have an active proposal on this job.',
                'error_code' => 'DUPLICATE_PROPOSAL',
            ], 422);
        }

        $validated = $request->validate([
            'cover_note' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1', 'max:10'], // 1..10 items per SRS FR-4.1
            'items.*.item_name' => ['required', 'string', 'max:255'],
            'items.*.description' => ['nullable', 'string'],
            'items.*.text_location' => ['nullable', 'string', 'max:255'],
            'items.*.estimated_item_price_santim' => ['nullable', 'integer', 'min:0'],
            'items.*.commission_amount_santim' => ['required', 'integer', 'min:10000'], // min 100 ETB floor (FR-4.2)
            'items.*.image_s3_keys' => ['nullable', 'array', 'max:5'],
            'items.*.image_s3_keys.*' => ['string'],
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
                    'text_location' => $itemData['text_location'] ?? null,
                    'estimated_item_price_santim' => $itemData['estimated_item_price_santim'] ?? null,
                    'commission_amount_santim' => $itemData['commission_amount_santim'],
                    'image_s3_keys' => $itemData['image_s3_keys'] ?? null,
                ]);
            }

            $job->increment('proposal_count');

            return $proposal;
        });

        // Notify buyer
        $job->buyer->notify(new NewProposalNotification($proposal));

        return response()->json([
            'message' => 'Proposal submitted successfully',
            'proposal' => $proposal->load('items'),
        ], 201);
    }

    /**
     * Withdraw a proposal (Delala only, strictly if pending) (SRS §4.2 Constraint 2).
     */
    public function withdraw(Request $request, Proposal $proposal): JsonResponse
    {
        if ($request->user()->id !== $proposal->delala_id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Delala can transition to WITHDRAWN ONLY while in PENDING state
        if ($proposal->status->getValue() !== 'pending') {
            return response()->json([
                'message' => 'Proposals can only be withdrawn while in pending state before negotiation begins.',
                'error_code' => 'CANNOT_WITHDRAW_NEGOTIATING_PROPOSAL',
            ], 422);
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
     * In-Chat Commission Mutation (SRS FR-4.4).
     */
    public function updateCommission(Request $request, Proposal $proposal, ProposalItem $item): JsonResponse
    {
        $user = $request->user();

        if ($user->id !== $proposal->delala_id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if ($item->proposal_id !== $proposal->id) {
            return response()->json(['message' => 'Item does not belong to this proposal.'], 404);
        }

        if ($item->status->getValue() !== 'pending') {
            return response()->json(['message' => 'Can only mutate commission for pending items.'], 422);
        }

        $validated = $request->validate([
            'commission_amount_santim' => ['required', 'integer', 'min:10000'],
        ]);

        $oldAmount = $item->commission_amount_santim;
        $newAmount = $validated['commission_amount_santim'];

        DB::transaction(function () use ($item, $oldAmount, $newAmount, $user) {
            $item->update(['commission_amount_santim' => $newAmount]);

            ProposalItemPriceHistory::create([
                'proposal_item_id' => $item->id,
                'old_amount_santim' => $oldAmount,
                'new_amount_santim' => $newAmount,
                'changed_by_id' => $user->id,
            ]);
        });

        // Broadcast event into chat stream
        broadcast(new CommissionUpdated($item->fresh(), $oldAmount, $newAmount))->toOthers();

        return response()->json([
            'message' => 'Commission updated successfully',
            'item' => $item->fresh(),
            'old_amount_santim' => $oldAmount,
            'new_amount_santim' => $newAmount,
        ]);
    }

    /**
     * Accept a single item in a proposal (Buyer only).
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
