<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class KycProfileResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'status'        => (string) $this->status,
            'document_type' => $this->document_type,
            'submitted_at'  => $this->updated_at?->toIso8601String(),
            'reviewed_at'   => $this->reviewed_at?->toIso8601String(),
            'rejection_reason' => $this->rejection_reason,
            // Pre-signed URLs for admin viewing (15 min TTL) — only shown to admin
            'id_document_url'      => $this->when(
                $request->user()?->isAdmin(),
                fn () => $this->id_document_s3_key
                    ? \Storage::disk('s3')->temporaryUrl($this->id_document_s3_key, now()->addMinutes(15))
                    : null,
            ),
            'id_document_back_url' => $this->when(
                $request->user()?->isAdmin(),
                fn () => $this->id_document_back_s3_key
                    ? \Storage::disk('s3')->temporaryUrl($this->id_document_back_s3_key, now()->addMinutes(15))
                    : null,
            ),
            'selfie_url'           => $this->when(
                $request->user()?->isAdmin(),
                fn () => $this->selfie_s3_key
                    ? \Storage::disk('s3')->temporaryUrl($this->selfie_s3_key, now()->addMinutes(15))
                    : null,
            ),
        ];
    }
}
