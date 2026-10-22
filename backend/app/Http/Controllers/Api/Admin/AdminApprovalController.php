<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminApprovalRequest;
use App\Models\EscrowTransaction;
use App\Services\ChapaService;
use App\Services\LedgerService;
use App\States\Escrow\ForceRefunded;
use App\States\Escrow\ForceReleased;
use App\States\Job\Canceled as JobCanceled;
use App\States\Job\Completed;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminApprovalController extends Controller
{
    /**
     * List Maker-Checker approval requests.
     */
    public function index(Request $request): JsonResponse
    {
        if (! $request->user()->isAdmin()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $query = AdminApprovalRequest::with(['requestedBy:id,full_name,email', 'approvedBy:id,full_name,email'])
            ->orderByDesc('created_at');

        if ($request->filled('status')) {
            $query->where('status', strtoupper($request->status));
        }

        return response()->json($query->paginate(20));
    }

    /**
     * Senior Admin approves a Maker-Checker request (SRS §3 & AR-2).
     */
    public function approve(Request $request, AdminApprovalRequest $approval, LedgerService $ledger, ChapaService $chapa): JsonResponse
    {
        $seniorAdmin = $request->user();

        if (! $seniorAdmin->isAdmin()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Strict Maker-Checker segregation: Maker cannot be Checker
        if ($seniorAdmin->id === $approval->requested_by_id) {
            return response()->json([
                'message' => 'Maker-Checker policy violation: Approver cannot be the same administrator who initiated the request.',
                'error_code' => 'MAKER_CHECKER_VIOLATION',
            ], 403);
        }

        if ($approval->status !== 'PENDING') {
            return response()->json([
                'message' => 'This request has already been '.strtolower($approval->status).'.',
            ], 422);
        }

        DB::transaction(function () use ($approval, $seniorAdmin, $ledger, $chapa) {
            if ($approval->action_type === 'FORCE_RELEASE') {
                $escrow = EscrowTransaction::findOrFail($approval->entity_id);
                $escrow->status->transitionTo(ForceReleased::class);
                $escrow->update([
                    'resolved_at' => now(),
                    'resolved_by' => $seniorAdmin->id,
                    'admin_resolution_note' => 'Maker-Checker approved force release: '.$approval->justification_note,
                ]);

                $ledger->recordEscrowReleased($escrow);

                if ($escrow->job->status->canTransitionTo(Completed::class)) {
                    $escrow->job->status->transitionTo(Completed::class);
                }
            } elseif ($approval->action_type === 'FORCE_REFUND') {
                $escrow = EscrowTransaction::findOrFail($approval->entity_id);
                $escrow->status->transitionTo(ForceRefunded::class);
                $escrow->update([
                    'resolved_at' => now(),
                    'resolved_by' => $seniorAdmin->id,
                    'admin_resolution_note' => 'Maker-Checker approved force refund: '.$approval->justification_note,
                ]);

                $chapa->refund($escrow);
                $ledger->recordRefund($escrow, 'Maker-Checker Approved Force Refund');

                if ($escrow->job->status->canTransitionTo(JobCanceled::class)) {
                    $escrow->job->status->transitionTo(JobCanceled::class);
                }
            }

            $approval->update([
                'status' => 'APPROVED',
                'approved_by_id' => $seniorAdmin->id,
                'approved_at' => now(),
            ]);
        });

        return response()->json([
            'message' => 'Maker-Checker approval confirmed and action dispatched to ledger.',
            'approval_request' => $approval->fresh(),
        ]);
    }

    /**
     * Senior Admin rejects a Maker-Checker request.
     */
    public function reject(Request $request, AdminApprovalRequest $approval): JsonResponse
    {
        $seniorAdmin = $request->user();

        if (! $seniorAdmin->isAdmin()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if ($seniorAdmin->id === $approval->requested_by_id) {
            return response()->json([
                'message' => 'Maker-Checker policy violation: Approver cannot be the same administrator who initiated the request.',
                'error_code' => 'MAKER_CHECKER_VIOLATION',
            ], 403);
        }

        if ($approval->status !== 'PENDING') {
            return response()->json([
                'message' => 'This request has already been '.strtolower($approval->status).'.',
            ], 422);
        }

        $validated = $request->validate([
            'rejection_reason' => ['required', 'string', 'max:1000'],
        ]);

        $approval->update([
            'status' => 'REJECTED',
            'approved_by_id' => $seniorAdmin->id,
            'rejection_reason' => $validated['rejection_reason'],
            'approved_at' => now(),
        ]);

        return response()->json([
            'message' => 'Maker-Checker approval request rejected.',
            'approval_request' => $approval->fresh(),
        ]);
    }
}
