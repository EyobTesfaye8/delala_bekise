<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Media\PresignRequest;
use App\Services\MediaService;
use Illuminate\Http\JsonResponse;

/**
 * Handles S3 pre-signed upload URL generation.
 * SRS §9.1: File binary NEVER passes through Laravel.
 */
class MediaController extends Controller
{
    public function __construct(private readonly MediaService $mediaService) {}

    /**
     * Generate a pre-signed PUT URL for direct S3 upload.
     *
     * POST /api/v1/media/presign
     */
    public function presign(PresignRequest $request): JsonResponse
    {
        $result = $this->mediaService->generatePresignedPut(
            fileType:      $request->input('file_type'),
            mimeType:      $request->input('mime_type'),
            fileSizeBytes: (int) $request->input('file_size_bytes'),
            userId:        $request->user()->id,
        );

        return response()->json([
            's3_key'       => $result['s3_key'],
            'presigned_url' => $result['presigned_url'],
            'expires_at'   => $result['expires_at'],
            'instructions' => 'PUT the file binary directly to presigned_url with the correct Content-Type header. Then use s3_key when submitting your form.',
        ]);
    }
}
