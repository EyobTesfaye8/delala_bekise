<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class MediaController extends Controller
{
    /**
     * Generate S3 Pre-Signed Upload URL (SRS §9.1).
     */
    public function presign(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'file_type' => ['required', 'string', Rule::in(['kyc_id', 'proposal_image', 'dispute_evidence'])],
            'mime_type' => ['required', 'string', function ($attribute, $value, $fail) use ($request) {
                $allowed = ['image/jpeg', 'image/png', 'image/webp'];
                if ($request->file_type === 'kyc_id' || $request->file_type === 'dispute_evidence') {
                    $allowed[] = 'application/pdf';
                }

                if (! in_array($value, $allowed, true)) {
                    $fail("The mime type '{$value}' is invalid for file type '{$request->file_type}'.");
                }
            }],
            'file_size_bytes' => ['required', 'integer', 'min:1024', 'max:5242880'], // max 5MB per SRS §9.1
        ]);

        $user = $request->user();
        $ext = match ($validated['mime_type']) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'application/pdf' => 'pdf',
            default => 'bin',
        };

        $prefix = match ($validated['file_type']) {
            'kyc_id' => 'kyc',
            'proposal_image' => 'proposals',
            'dispute_evidence' => 'disputes',
        };

        $s3Key = "{$prefix}/{$user->id}/".(string) Str::uuid().".{$ext}";

        // Testing disk fallback
        if (app()->environment('testing')) {
            return response()->json([
                'upload_url' => "https://s3.mock.local/{$s3Key}",
                's3_key' => $s3Key,
                'expires_in' => 600,
                'headers' => [
                    'Content-Type' => $validated['mime_type'],
                ],
            ]);
        }

        $presigned = Storage::disk('s3')->temporaryUploadUrl(
            $s3Key,
            now()->addMinutes(10),
            ['ContentType' => $validated['mime_type']]
        );

        return response()->json([
            'upload_url' => $presigned['url'],
            's3_key' => $s3Key,
            'expires_in' => 600,
            'headers' => array_merge($presigned['headers'] ?? [], [
                'Content-Type' => $validated['mime_type'],
            ]),
        ]);
    }
}
