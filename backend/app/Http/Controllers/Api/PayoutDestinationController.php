<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PayoutDestination;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PayoutDestinationController extends Controller
{
    /**
     * List all payout destinations for authenticated Delala (FR-2.4).
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->isDelala()) {
            return response()->json(['message' => 'Only Delalas have payout destinations.'], 403);
        }

        $destinations = PayoutDestination::where('delala_id', $user->id)
            ->orderByDesc('is_default')
            ->orderBy('created_at')
            ->get();

        return response()->json($destinations);
    }

    /**
     * Register a new payout destination (FR-2.4).
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->isDelala()) {
            return response()->json(['message' => 'Only Delalas can register payout destinations.'], 403);
        }

        $validated = $request->validate([
            'bank_code' => ['required', 'string', 'max:50'],
            'bank_name' => ['required', 'string', 'max:255'],
            'account_number' => ['required', 'string', 'max:100'],
            'account_holder_name' => ['required', 'string', 'max:255'],
            'telebirr_number' => ['nullable', 'string', 'max:50'],
            'is_default' => ['nullable', 'boolean'],
        ]);

        $destination = DB::transaction(function () use ($user, $validated) {
            $isFirst = ! PayoutDestination::where('delala_id', $user->id)->exists();
            $makeDefault = $isFirst || ! empty($validated['is_default']);

            if ($makeDefault) {
                PayoutDestination::where('delala_id', $user->id)->update(['is_default' => false]);
            }

            return PayoutDestination::create([
                'delala_id' => $user->id,
                'bank_code' => strtoupper($validated['bank_code']),
                'bank_name' => $validated['bank_name'],
                'account_number' => $validated['account_number'],
                'account_holder_name' => $validated['account_holder_name'],
                'telebirr_number' => $validated['telebirr_number'] ?? null,
                'is_default' => $makeDefault,
            ]);
        });

        return response()->json([
            'message' => 'Payout destination registered successfully.',
            'data' => $destination,
        ], 201);
    }

    /**
     * Set a payout destination as default.
     */
    public function setDefault(Request $request, PayoutDestination $payoutDestination): JsonResponse
    {
        if ($request->user()->id !== $payoutDestination->delala_id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        DB::transaction(function () use ($payoutDestination) {
            PayoutDestination::where('delala_id', $payoutDestination->delala_id)->update(['is_default' => false]);
            $payoutDestination->update(['is_default' => true]);
        });

        return response()->json([
            'message' => 'Default payout destination updated.',
            'data' => $payoutDestination->fresh(),
        ]);
    }

    /**
     * Delete a payout destination.
     */
    public function destroy(Request $request, PayoutDestination $payoutDestination): JsonResponse
    {
        if ($request->user()->id !== $payoutDestination->delala_id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $wasDefault = $payoutDestination->is_default;
        $delalaId = $payoutDestination->delala_id;

        $payoutDestination->delete();

        // If it was default, promote another if available
        if ($wasDefault) {
            $next = PayoutDestination::where('delala_id', $delalaId)->first();
            if ($next) {
                $next->update(['is_default' => true]);
            }
        }

        return response()->json([
            'message' => 'Payout destination deleted successfully.',
        ]);
    }
}
