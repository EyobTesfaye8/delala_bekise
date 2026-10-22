<?php

namespace App\Services;

use App\Models\DelalaJob;
use App\Models\Proposal;
use App\Models\ProposalItem;
use App\Models\ProposalItemPriceHistory;
use App\Models\User;
use App\Models\States\Negotiating;
use App\Models\States\ProposalNegotiating;
use App\Models\States\ProposalWithdrawn;
use App\Services\AuditLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Proposal submission and negotiation service.
 * SRS FR-4.1 – FR-4.5.
 */
class ProposalService
{
    public function __construct(private readonly AuditLogService $auditLog) {}

    /**
     * Submit a proposal for an open job.
     * SRS FR-4.1: Transitions job OPEN → NEGOTIATING on first proposal.
     * SRS FR-4.2: Up to 3 item lines per proposal.
     */
    public function submit(User $delala, DelalaJob $job, array $data, Request $request): Proposal
    {
        if (! $job->isOpen() && ! $job->isNegotiating()) {
            throw new \Exception('JOB_NOT_ACCEPTING_PROPOSALS', 422);
        }

        // Limit: max 5 active proposals per job (SRS FR-4.1)
        if ($job->proposals()->whereIn('status', ['proposal_pending', 'proposal_negotiating'])->count() >= 5) {
            throw new \Exception('PROPOSAL_LIMIT_REACHED', 429);
        }

        // Prevent duplicate proposal from same Delala
        if ($job->proposals()->where('delala_id', $delala->id)->whereNotIn('status', ['proposal_rejected', 'proposal_withdrawn'])->exists()) {
            throw new \Exception('DUPLICATE_PROPOSAL', 409);
        }

        return DB::transaction(function () use ($delala, $job, $data, $request) {
            $proposal = Proposal::create([
                'job_id'     => $job->id,
                'delala_id'  => $delala->id,
                'cover_note' => $data['cover_note'] ?? null,
            ]);

            // Create proposal items (max 3 per SRS FR-4.2)
            $items = array_slice($data['items'] ?? [], 0, 3);
            foreach ($items as $itemData) {
                ProposalItem::create([
                    'proposal_id'                  => $proposal->id,
                    'item_name'                    => $itemData['item_name'],
                    'description'                  => $itemData['description'] ?? null,
                    'text_location'                => $itemData['text_location'] ?? null,
                    'image_s3_keys'                => $itemData['image_s3_keys'] ?? [],
                    'estimated_item_price_santim'  => $itemData['estimated_item_price_santim'] ?? null,
                    'commission_amount_santim'     => $itemData['commission_amount_santim'],
                ]);
            }

            // Transition job to NEGOTIATING on first proposal
            if ($job->isOpen()) {
                $job->status->transitionTo(Negotiating::class);
            }

            $this->auditLog->log('proposal_submitted', Proposal::class, $proposal->id, [], [
                'job_id'    => $job->id,
                'delala_id' => $delala->id,
            ], $request);

            return $proposal->load('items');
        });
    }

    /**
     * Delala withdraws their proposal.
     * SRS FR-4.3.
     */
    public function withdraw(Proposal $proposal, Request $request): void
    {
        if (! $proposal->isPending() && ! $proposal->isNegotiating()) {
            throw new \Exception('PROPOSAL_CANNOT_BE_WITHDRAWN', 422);
        }

        DB::transaction(function () use ($proposal, $request) {
            $proposal->status->transitionTo(ProposalWithdrawn::class);
            $this->auditLog->log('proposal_withdrawn', Proposal::class, $proposal->id, [], [], $request);
        });
    }

    /**
     * Buyer/Delala negotiates commission on a proposal item.
     * SRS FR-4.4: Records price history for every change.
     */
    public function updateCommission(
        Proposal $proposal,
        ProposalItem $item,
        int $newAmountSantim,
        User $actor,
        Request $request,
    ): ProposalItem {
        if (! $proposal->isPending() && ! $proposal->isNegotiating()) {
            throw new \Exception('PROPOSAL_NOT_IN_NEGOTIATION', 422);
        }

        return DB::transaction(function () use ($proposal, $item, $newAmountSantim, $actor, $request) {
            // Ensure proposal is in NEGOTIATING state
            if ($proposal->isPending()) {
                $proposal->status->transitionTo(ProposalNegotiating::class);
            }

            // Record history
            ProposalItemPriceHistory::create([
                'proposal_item_id'   => $item->id,
                'changed_by_id'      => $actor->id,
                'old_amount_santim'  => $item->commission_amount_santim,
                'new_amount_santim'  => $newAmountSantim,
            ]);

            $item->update(['commission_amount_santim' => $newAmountSantim]);

            $this->auditLog->log('commission_updated', ProposalItem::class, $item->id, [
                'old_santim' => $item->getOriginal('commission_amount_santim'),
            ], ['new_santim' => $newAmountSantim], $request);

            return $item->fresh();
        });
    }
}
