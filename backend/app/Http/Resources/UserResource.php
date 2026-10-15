<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'                  => $this->guest_uuid ?? (string) $this->id,
            'full_name'           => $this->full_name,
            'phone'               => $this->phone,
            'email'               => $this->email,
            'type'                => $this->type,
            'status'              => $this->status,
            'phone_verified'      => ! is_null($this->phone_verified_at),
            'email_verified'      => ! is_null($this->email_verified_at),
            'kyc_status'          => $this->whenLoaded('kycProfile', fn () => $this->kycProfile?->status),
            'avatar_url'          => $this->avatar_s3_key
                ? \Storage::disk('s3')->temporaryUrl($this->avatar_s3_key, now()->addMinutes(15))
                : null,
            'member_since'        => $this->created_at?->toIso8601String(),
        ];
    }
}
