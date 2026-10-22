<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EscrowResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id'                       => $this->uuid,
            'status'                   => (string) $this->status,
            'commission_amount_santim' => $this->commission_amount_santim,
            'commission_etb'           => $this->commission_amount_santim / 100,
            'platform_fee_santim'      => $this->platform_fee_santim,
            'delala_amount_santim'     => $this->delala_amount_santim,
            'checkout_url'             => $this->when(
                $this->isPendingPayment(),
                $this->chapa_checkout_url,
            ),
            'chapa_paid_at'            => $this->chapa_paid_at?->toIso8601String(),
            'handover_declared_at'     => $this->handover_declared_at?->toIso8601String(),
            'auto_release_at'          => $this->auto_release_at?->toIso8601String(),
            'cancellation_deadline_at' => $this->cancellation_deadline_at?->toIso8601String(),
            'dispute_reason_code'      => $this->dispute_reason_code,
            'created_at'               => $this->created_at?->toIso8601String(),
        ];
    }
}
