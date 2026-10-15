<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Kyc\SubmitKycRequest;
use App\Http\Resources\KycProfileResource;
use App\Services\KycService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * KYC submission and status endpoints for Delala users.
 * SRS FR-2.1, FR-2.3.
 */
class KycController extends Controller
{
    public function __construct(private readonly KycService $kycService) {}

    /**
     * Get current KYC status.
     * GET /api/v1/kyc/status
     */
    public function status(Request $request): JsonResponse
    {
        $user = $request->user();
        $kyc  = $user->kycProfile;

        if (! $kyc) {
            return response()->json([
                'kyc_status' => 'pending_submission',
                'message'    => 'You have not submitted your KYC documents yet.',
            ]);
        }

        return response()->json([
            'kyc_status' => (string) $kyc->status,
            'profile'    => new KycProfileResource($kyc),
        ]);
    }

    /**
     * Submit or resubmit KYC documents.
     * POST /api/v1/kyc/submit
     * SRS FR-2.3: Kebele ID front+back (or Passport), plus selfie.
     */
    public function submit(SubmitKycRequest $request): JsonResponse
    {
        $kyc = $this->kycService->submit(
            delala: $request->user(),
            data: $request->validated(),
            request: $request,
        );

        return response()->json([
            'message' => 'KYC documents submitted successfully. Your application is now under review.',
            'profile' => new KycProfileResource($kyc),
        ], 201);
    }
}
