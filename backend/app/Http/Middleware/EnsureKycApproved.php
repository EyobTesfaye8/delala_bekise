<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureKycApproved
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->isDelala()) {
            if (! $user->kycProfile || ! $user->kycProfile->status->equals(\App\States\Kyc\Approved::class)) {
                return response()->json([
                    'message' => 'Your KYC profile must be approved to perform this action.'
                ], 403);
            }
        }

        return $next($request);
    }
}
