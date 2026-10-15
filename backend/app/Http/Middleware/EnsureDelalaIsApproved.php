<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks any Delala not in APPROVED KYC state.
 * SRS FR-2.2.
 */
class EnsureDelalaIsApproved
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->isDelala()) {
            return response()->json([
                'error' => 'FORBIDDEN',
                'message' => 'This endpoint is restricted to verified Delalas.',
            ], 403);
        }

        $kyc = $user->kycProfile;

        if (! $kyc || ! $kyc->isApproved()) {
            $status = $kyc?->status->getValue() ?? 'pending_submission';

            return response()->json([
                'error' => 'KYC_NOT_APPROVED',
                'message' => 'Your KYC application must be approved before accessing this feature.',
                'kyc_status' => $status,
            ], 403);
        }

        return $next($request);
    }
}
