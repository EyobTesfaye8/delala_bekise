<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Payout\WithdrawRequest;
use App\Http\Resources\PayoutRequestResource;
use App\Models\PayoutDestination;
use App\Models\PayoutRequest;
use App\Services\LedgerService;
use App\Services\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Payout withdrawal controller for Delalas.
 * SRS FR-2.5.
 */
class PayoutController extends Controller
{
    public function __construct(
        private readonly LedgerService   $ledger,
        private readonly AuditLogService $auditLog,
    ) {}

    /** POST /api/v1/payouts/withdraw */
    public function withdraw(WithdrawRequest $request): JsonResponse
    {
        $delala      = $request->user();
        $amountSantim = (int) $request->input('amount_santim');

        // Find the destination
        $destination = PayoutDestination::where('uuid', $request->input('payout_destination_uuid'))
            ->where('delala_id', $delala->id)
            ->firstOrFail();

        // Check available wallet balance via ledger
        $available = $this->getAvailableBalance($delala->id);

        if ($available < $amountSantim) {
            return response()->json([
                'error'              => 'INSUFFICIENT_BALANCE',
                'available_santim'   => $available,
                'requested_santim'   => $amountSantim,
            ], 422);
        }

        $payoutRef = 'PAY-'.Str::upper(Str::random(12));

        $payout = DB::transaction(function () use ($delala, $amountSantim, $destination, $payoutRef, $request) {
            $payout = PayoutRequest::create([
                'delala_id'              => $delala->id,
                'amount_santim'          => $amountSantim,
                'status'                 => 'pending',
                'payment_method'         => $destination->method,
                'bank_name'              => $destination->bank_name,
                'account_number'         => $destination->account_number,
                'payout_destination_id'  => $destination->id,
                'batch_ref'              => $payoutRef,
            ]);

            $this->ledger->recordWithdrawalRequest($delala->id, $amountSantim, $payoutRef);

            $this->auditLog->log('payout_requested', PayoutRequest::class, $payout->id, [], [
                'amount_santim' => $amountSantim,
                'payout_ref'    => $payoutRef,
            ], $request);

            return $payout;
        });

        return response()->json([
            'message' => 'Withdrawal request submitted. Admin will process within 2 business days.',
            'payout'  => new PayoutRequestResource($payout),
        ], 201);
    }

    /** GET /api/v1/payouts/history */
    public function history(Request $request): JsonResponse
    {
        $payouts = PayoutRequest::where('delala_id', $request->user()->id)
            ->latest()
            ->paginate(20);

        return response()->json([
            'payouts'           => PayoutRequestResource::collection($payouts->items()),
            'available_santim'  => $this->getAvailableBalance($request->user()->id),
            'meta'              => [
                'current_page' => $payouts->currentPage(),
                'last_page'    => $payouts->lastPage(),
                'total'        => $payouts->total(),
            ],
        ]);
    }

    private function getAvailableBalance(int $userId): int
    {
        // Sum credits minus debits on DELALA_AVAILABLE_WALLET for this user
        return (int) \App\Models\LedgerAccount::where('code', \App\Models\LedgerAccount::DELALA_AVAILABLE_WALLET)
            ->where('user_id', $userId)
            ->first()
            ?->balanceSantim() ?? 0;
    }
}
