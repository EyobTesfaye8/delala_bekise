<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Proposal\StoreProposalRequest;
use App\Http\Requests\Proposal\UpdateCommissionRequest;
use App\Http\Resources\ProposalResource;
use App\Models\DelalaJob;
use App\Models\Proposal;
use App\Models\ProposalItem;
use App\Services\ProposalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Proposal controller.
 * SRS FR-4.1 – FR-4.5.
 */
class ProposalController extends Controller
{
    public function __construct(private readonly ProposalService $proposalService) {}

    /** POST /api/v1/proposals */
    public function store(StoreProposalRequest $request): JsonResponse
    {
        $request->validate(['job_uuid' => 'required|string|exists:delala_jobs,uuid']);
        $job = DelalaJob::where('uuid', $request->input('job_uuid'))->firstOrFail();

        try {
            $proposal = $this->proposalService->submit($request->user(), $job, $request->validated(), $request);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], (int) $e->getCode() ?: 422);
        }

        return response()->json([
            'message'  => 'Proposal submitted successfully.',
            'proposal' => new ProposalResource($proposal->load('delala', 'items')),
        ], 201);
    }

    /** GET /api/v1/proposals/{proposal} */
    public function show(Request $request, Proposal $proposal): JsonResponse
    {
        $user = $request->user();
        $job  = $proposal->job;

        // Only buyer of the job OR the delala who submitted may view
        if ($user->id !== $job->buyer_id && $user->id !== $proposal->delala_id && ! $user->isAdmin()) {
            return response()->json(['error' => 'FORBIDDEN'], 403);
        }

        return response()->json([
            'proposal' => new ProposalResource($proposal->load('delala', 'items')),
        ]);
    }

    /** POST /api/v1/proposals/{proposal}/withdraw */
    public function withdraw(Request $request, Proposal $proposal): JsonResponse
    {
        if ($request->user()->id !== $proposal->delala_id) {
            return response()->json(['error' => 'FORBIDDEN'], 403);
        }

        try {
            $this->proposalService->withdraw($proposal, $request);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json(['message' => 'Proposal withdrawn successfully.']);
    }

    /** PATCH /api/v1/proposals/{proposal}/items/{item}/commission */
    public function updateCommission(
        UpdateCommissionRequest $request,
        Proposal $proposal,
        ProposalItem $item,
    ): JsonResponse {
        $user = $request->user();

        // Only buyer of the job or delala of the proposal may update commission
        $job = $proposal->job;
        if ($user->id !== $job->buyer_id && $user->id !== $proposal->delala_id) {
            return response()->json(['error' => 'FORBIDDEN'], 403);
        }

        try {
            $item = $this->proposalService->updateCommission(
                $proposal,
                $item,
                (int) $request->input('commission_amount_santim'),
                $user,
                $request,
            );
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Commission updated.',
            'item'    => [
                'id'                       => $item->uuid,
                'commission_amount_santim' => $item->commission_amount_santim,
                'commission_etb'           => $item->commissionInEtb(),
            ],
        ]);
    }

    /** GET /api/v1/proposals/my/submitted — Delala's proposals */
    public function myProposals(Request $request): JsonResponse
    {
        $proposals = Proposal::with(['job.category', 'items'])
            ->where('delala_id', $request->user()->id)
            ->latest()
            ->paginate(20);

        return response()->json([
            'proposals' => ProposalResource::collection($proposals->items()),
            'meta'      => [
                'current_page' => $proposals->currentPage(),
                'last_page'    => $proposals->lastPage(),
                'total'        => $proposals->total(),
            ],
        ]);
    }
}
