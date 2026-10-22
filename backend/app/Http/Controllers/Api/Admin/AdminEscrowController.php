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

class AdminEscrowController extends Controller
{
    private const MAKER_CHECKER_THRESHOLD_SANTIM = 1000000; // 10,000 ETB

    /**
     * Admin Force Release (SRS §3 & AR-2).
     */
    public function forceRelease(Request $request, EscrowTransaction $escrow, LedgerService $ledger): JsonResponse
    {
        $admin = $request->user();

        if (! $admin->isAdmin()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'justification_note' => ['required', 'string', 'max:1000'],
        ]);

        $amountSantim = $escrow->delala_amount_santim;

        // Maker-Checker Threshold Check
        if ($amountSantim > self::MAKER_CHECKER_THRESHOLD_SANTIM) {
            $approval = AdminApprovalRequest::create([
                'action_type' => 'FORCE_RELEASE',
                'entity_type' => EscrowTransaction::class,
                'entity_id' => $escrow->id,
                'amount_santim' => $amountSantim,
                'requested_by_id' => $admin->id,
                'status' => 'PENDING',
                'justification_note' => $validated['justification_note'],
            ]);

            return response()->json([
                'message' => 'Action exceeds 10,000 ETB Maker-Checker threshold. Queued for Senior Admin approval.',
                'requires_maker_checker' => true,
                'approval_request' => $approval,
            ], 202);
        }

        // Direct Execution for <= 10,000 ETB
        DB::transaction(function () use ($escrow, $ledger, $admin, $validated) {
            $escrow->status->transitionTo(ForceReleased::class);
            $escrow->update([
                'resolved_at' => now(),
                'resolved_by' => $admin->id,
                'admin_resolution_note' => 'Force release: '.$validated['justification_note'],
            ]);

            $ledger->recordEscrowReleased($escrow);

            if ($escrow->job->status->canTransitionTo(Completed::class)) {
                $escrow->job->status->transitionTo(Completed::class);
            }
        });

        return response()->json([
            'message' => 'Funds force-released to Delala available wallet successfully.',
            'escrow' => $escrow->fresh(),
        ]);
    }

    /**
     * Admin Force Refund (SRS §3 & AR-2).
     */
    public function forceRefund(Request $request, EscrowTransaction $escrow, ChapaService $chapa, LedgerService $ledger): JsonResponse
    {
        $admin = $request->user();

        if (! $admin->isAdmin()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'justification_note' => ['required', 'string', 'max:1000'],
        ]);

        $amountSantim = $escrow->commission_amount_santim;

        // Maker-Checker Threshold Check
        if ($amountSantim > self::MAKER_CHECKER_THRESHOLD_SANTIM) {
            $approval = AdminApprovalRequest::create([
                'action_type' => 'FORCE_REFUND',
                'entity_type' => EscrowTransaction::class,
                'entity_id' => $escrow->id,
                'amount_santim' => $amountSantim,
                'requested_by_id' => $admin->id,
                'status' => 'PENDING',
                'justification_note' => $validated['justification_note'],
            ]);

            return response()->json([
                'message' => 'Action exceeds 10,000 ETB Maker-Checker threshold. Queued for Senior Admin approval.',
                'requires_maker_checker' => true,
                'approval_request' => $approval,
            ], 202);
        }

        // Direct Execution for <= 10,000 ETB
        DB::transaction(function () use ($escrow, $chapa, $ledger, $admin, $validated) {
            $escrow->status->transitionTo(ForceRefunded::class);
            $escrow->update([
                'resolved_at' => now(),
                'resolved_by' => $admin->id,
                'admin_resolution_note' => 'Force refund: '.$validated['justification_note'],
            ]);

            $chapa->refund($escrow);
            $ledger->recordRefund($escrow, 'Admin Force Refund: '.$validated['justification_note']);

            if ($escrow->job->status->canTransitionTo(JobCanceled::class)) {
                $escrow->job->status->transitionTo(JobCanceled::class);
            }
        });

        return response()->json([
            'message' => 'Escrow force-refunded to Buyer successfully.',
            'escrow' => $escrow->fresh(),
        ]);
    }
}
