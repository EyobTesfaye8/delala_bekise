<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\KycProfile;
use App\Rules\S3ObjectExists;
use App\States\Kyc\Approved;
use App\States\Kyc\UnderReview;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class KycController extends Controller
{
    /**
     * Get authenticated Delala's KYC profile and status.
     */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->isDelala()) {
            return response()->json(['message' => 'Only Delalas have a KYC profile.'], 403);
        }

        $kyc = $user->kycProfile;

        if (! $kyc) {
            return response()->json([
                'status' => 'pending_submission',
                'profile' => null,
            ]);
        }

        // Generate temporary viewing URLs for documents
        $idFrontUrl = null;
        $idBackUrl = null;
        $selfieUrl = null;

        try {
            if ($kyc->id_document_s3_key) {
                $idFrontUrl = Storage::disk('s3')->temporaryUrl($kyc->id_document_s3_key, now()->addMinutes(15));
            }
            if ($kyc->id_document_back_s3_key) {
                $idBackUrl = Storage::disk('s3')->temporaryUrl($kyc->id_document_back_s3_key, now()->addMinutes(15));
            }
            if ($kyc->selfie_s3_key) {
                $selfieUrl = Storage::disk('s3')->temporaryUrl($kyc->selfie_s3_key, now()->addMinutes(15));
            }
        } catch (\Throwable $e) {
            // S3 temporaryUrl not available in local mock
        }

        return response()->json([
            'status' => $kyc->status->getValue(),
            'profile' => $kyc,
            'urls' => [
                'id_front_url' => $idFrontUrl,
                'id_back_url' => $idBackUrl,
                'selfie_url' => $selfieUrl,
            ],
        ]);
    }

    /**
     * Submit KYC documents using Pre-Signed S3 Keys (SRS §6.2 & §9.1).
     */
    public function submit(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->isDelala()) {
            return response()->json(['message' => 'Only Delalas can submit KYC.'], 403);
        }

        $kyc = $user->kycProfile;

        // If they already have an approved profile, or one under review, reject new submission
        if ($kyc && in_array($kyc->status->getValue(), ['approved', 'under_review'], true)) {
            return response()->json([
                'message' => 'You already have an active or pending KYC submission.',
            ], 400);
        }

        $validated = $request->validate([
            'document_type' => ['required', 'string', Rule::in(['national_id', 'passport', 'kebele_id', 'drivers_license'])],
            'id_document_s3_key' => ['required', 'string', new S3ObjectExists],
            'id_document_back_s3_key' => ['nullable', 'string', new S3ObjectExists],
            'selfie_s3_key' => ['required', 'string', new S3ObjectExists],
        ]);

        if (! $kyc) {
            $kyc = new KycProfile;
            $kyc->user_id = $user->id;
        }

        $kyc->document_type = $validated['document_type'];
        $kyc->id_document_s3_key = $validated['id_document_s3_key'];
        $kyc->id_document_back_s3_key = $validated['id_document_back_s3_key'] ?? null;
        $kyc->selfie_s3_key = $validated['selfie_s3_key'];
        $kyc->rejection_reason = null;

        $kyc->save();

        if ($kyc->status->canTransitionTo(UnderReview::class)) {
            $kyc->status->transitionTo(UnderReview::class);
        }

        return response()->json([
            'message' => 'KYC documents submitted successfully. Please wait for admin approval.',
            'profile' => $kyc->fresh(),
        ], 201);
    }
}
