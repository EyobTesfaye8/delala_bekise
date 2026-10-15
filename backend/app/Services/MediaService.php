<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Handles S3 pre-signed URL generation.
 * SRS §9.1: File binary NEVER passes through the Laravel application server.
 */
class MediaService
{
    /**
     * Allowed MIME types and their extensions.
     *
     * @var array<string, string>
     */
    private const MIME_EXTENSIONS = [
        'image/jpeg'      => 'jpg',
        'image/png'       => 'png',
        'image/webp'      => 'webp',
        'application/pdf' => 'pdf',
    ];

    /**
     * S3 folder prefixes by file type.
     *
     * @var array<string, string>
     */
    private const FOLDER_MAP = [
        'kyc_id'           => 'kyc/ids',
        'kyc_selfie'       => 'kyc/selfies',
        'proposal_image'   => 'proposals/images',
        'dispute_evidence' => 'disputes/evidence',
    ];

    /**
     * Generate a 10-minute pre-signed PUT URL.
     *
     * @return array{s3_key: string, presigned_url: string, expires_at: string}
     */
    public function generatePresignedPut(
        string $fileType,
        string $mimeType,
        int $fileSizeBytes,
        int $userId,
    ): array {
        $folder    = self::FOLDER_MAP[$fileType] ?? 'uploads';
        $extension = self::MIME_EXTENSIONS[$mimeType] ?? 'bin';
        $s3Key     = "{$folder}/{$userId}/".Str::uuid().'.'.$extension;

        $disk = Storage::disk('s3');

        // Generate presigned PUT URL valid for 10 minutes
        $presignedUrl = $disk->temporaryUploadUrl(
            $s3Key,
            now()->addMinutes(10),
            [
                'ContentType'   => $mimeType,
                'ContentLength' => $fileSizeBytes,
            ],
        );

        return [
            's3_key'       => $s3Key,
            'presigned_url' => $presignedUrl,
            'expires_at'   => now()->addMinutes(10)->toIso8601String(),
        ];
    }

    /**
     * Verify that an uploaded object exists in S3.
     * Called during form validation before saving DB records (SRS §9.1 step 4).
     */
    public function objectExists(string $s3Key): bool
    {
        return Storage::disk('s3')->exists($s3Key);
    }

    /**
     * Generate a 15-minute pre-signed GET URL for viewing KYC/dispute docs.
     */
    public function temporaryUrl(string $s3Key, int $minutes = 15): string
    {
        return Storage::disk('s3')->temporaryUrl($s3Key, now()->addMinutes($minutes));
    }
}
