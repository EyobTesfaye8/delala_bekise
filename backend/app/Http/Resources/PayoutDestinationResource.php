<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PayoutDestinationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'                    => $this->uuid,
            'method'                => $this->method,
            'bank_name'             => $this->bank_name,
            'account_holder_name'   => $this->account_holder_name,
            // Mask account number: show last 4 digits only
            'account_number_masked' => '****'.substr($this->account_number, -4),
            'is_default'            => $this->is_default,
            'is_verified_by_admin'  => $this->is_verified_by_admin,
            'created_at'            => $this->created_at?->toIso8601String(),
        ];
    }
}
