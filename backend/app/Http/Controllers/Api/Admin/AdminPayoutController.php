<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\PayoutRequest;
use App\Services\LedgerService;
use App\States\Payout\Paid;
use App\States\Payout\Pending;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminPayoutController extends Controller
{
    /**
     * List all payout requests (AR-3).
     */
    public function index(Request $request): JsonResponse
    {
        if (! $request->user()->isAdmin()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $query = PayoutRequest::with(['delala:id,full_name,phone', 'processor:id,full_name'])
            ->orderByDesc('created_at');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        return response()->json($query->paginate(20));
    }

    /**
     * Export pending payouts to bank-compatible CSV batch file (SRS AR-3).
     */
    public function exportBatchCsv(Request $request): StreamedResponse|JsonResponse
    {
        if (! $request->user()->isAdmin()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $pendingPayouts = PayoutRequest::with('delala')
            ->where('status', Pending::$name)
            ->get();

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="delala_payout_batch_'.now()->format('Ymd_His').'.csv"',
        ];

        return response()->stream(function () use ($pendingPayouts) {
            $handle = fopen('php://output', 'w');

            // CSV Header
            fputcsv($handle, [
                'Payout ID',
                'Delala Name',
                'Delala Phone',
                'Bank Name',
                'Account Number',
                'Telebirr Number',
                'Payment Method',
                'Amount (Santim)',
                'Amount (ETB)',
                'Requested At',
            ]);

            foreach ($pendingPayouts as $payout) {
                fputcsv($handle, [
                    $payout->id,
                    $payout->delala->full_name ?? 'Delala #'.$payout->delala_id,
                    $payout->delala->phone ?? '',
                    $payout->bank_name ?? 'N/A',
                    $payout->account_number ?? 'N/A',
                    $payout->telebirr_number ?? 'N/A',
                    $payout->payment_method,
                    $payout->amount_santim,
                    number_format($payout->amount_santim / 100, 2, '.', ''),
                    $payout->created_at->toIso8601String(),
                ]);
            }

            fclose($handle);
        }, 200, $headers);
    }

    /**
     * Settle payout after manual bank/Telebirr transfer (JNL-4B) (SRS AR-3).
     */
    public function settle(Request $request, PayoutRequest $payout, LedgerService $ledger): JsonResponse
    {
        $admin = $request->user();

        if (! $admin->isAdmin()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if ($payout->status->getValue() !== 'pending') {
            return response()->json(['message' => 'Payout is not in pending status.'], 422);
        }

        $validated = $request->validate([
            'bank_reference' => ['required', 'string', 'max:255'],
            'admin_note' => ['nullable', 'string', 'max:1000'],
        ]);

        DB::transaction(function () use ($payout, $admin, $validated, $ledger) {
            $payout->status->transitionTo(Paid::class);
            $payout->update([
                'processed_by' => $admin->id,
                'paid_at' => now(),
                'admin_note' => 'Bank Ref: '.$validated['bank_reference'].($validated['admin_note'] ? ' | '.$validated['admin_note'] : ''),
            ]);

            // Clear payout clearing against system holding (JNL-4B)
            $ledger->recordWithdrawalSettled(
                $payout->delala_id,
                $payout->amount_santim,
                $validated['bank_reference']
            );
        });

        return response()->json([
            'message' => 'Payout settled successfully and recorded in double-entry ledger.',
            'payout' => $payout->fresh(),
        ]);
    }
}
