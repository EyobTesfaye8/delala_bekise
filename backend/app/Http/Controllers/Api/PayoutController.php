<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PayoutDestination;
use App\Models\PayoutRequest;
use App\Services\LedgerService;
use App\States\Payout\Pending;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PayoutController extends Controller
{
    /**
     * Get Delala's payout requests and real-time available balance.
     */
    public function index(Request $request, LedgerService $ledger): JsonResponse
    {
        $user = $request->user();

        if (! $user->isDelala()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $balanceSantim = $ledger->getDelalaAvailableBalance($user->id);

        $requests = PayoutRequest::where('delala_id', $user->id)
            ->orderByDesc('created_at')
            ->paginate(15);

        return response()->json([
            'available_balance_santim' => $balanceSantim,
            'available_balance_etb' => $balanceSantim / 100,
            'payout_requests' => $requests,
        ]);
    }

    /**
     * Request a withdrawal from Available Balance (FR-2.5).
     */
    public function requestPayout(Request $request, LedgerService $ledger): JsonResponse
    {
        $user = $request->user();

        if (! $user->isDelala()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'amount_santim' => ['required', 'integer', 'min:50000'], // Min 500 ETB
            'payout_destination_id' => ['nullable', 'exists:payout_destinations,id'],
        ]);

        // Max 1 pending request at a time
        $existingPending = PayoutRequest::where('delala_id', $user->id)
            ->where('status', Pending::$name)
            ->exists();

        if ($existingPending) {
            return response()->json([
                'message' => 'You already have a pending payout request in progress.',
                'error_code' => 'DUPLICATE_PAYOUT_REQUEST',
            ], 422);
        }

        // Verify available balance
        $availableSantim = $ledger->getDelalaAvailableBalance($user->id);
        if ($validated['amount_santim'] > $availableSantim) {
            return response()->json([
                'message' => 'Requested amount exceeds your available balance.',
                'error_code' => 'INSUFFICIENT_BALANCE',
                'available_balance_santim' => $availableSantim,
            ], 422);
        }

        // Resolve payout destination
        $destination = null;
        if (! empty($validated['payout_destination_id'])) {
            $destination = PayoutDestination::where('id', $validated['payout_destination_id'])
                ->where('delala_id', $user->id)
                ->first();
        }

        if (! $destination) {
            $destination = PayoutDestination::where('delala_id', $user->id)
                ->where('is_default', true)
                ->first();
        }

        if (! $destination) {
            return response()->json([
                'message' => 'Please register a default payout destination before requesting a withdrawal.',
                'error_code' => 'NO_PAYOUT_DESTINATION',
            ], 422);
        }

        $payout = DB::transaction(function () use ($user, $validated, $destination, $ledger) {
            $payout = PayoutRequest::create([
                'delala_id' => $user->id,
                'amount_santim' => $validated['amount_santim'],
                'bank_name' => $destination->bank_name,
                'account_number' => $destination->account_number,
                'telebirr_number' => $destination->telebirr_phone,
                'payment_method' => $destination->bank_code === 'TELEBIRR' ? 'telebirr' : 'bank_transfer',
            ]);

            // Immediately reserve funds by moving to clearing account (JNL-4A)
            $ledger->recordWithdrawalRequested(
                $user->id,
                $validated['amount_santim'],
                'PO-'.$payout->id
            );

            return $payout;
        });

        return response()->json([
            'message' => 'Payout request submitted successfully and queued for batch processing.',
            'payout' => $payout,
        ], 201);
    }
}
