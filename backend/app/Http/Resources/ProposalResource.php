<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProposalResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->uuid,
            'status'     => (string) $this->status,
            'cover_note' => $this->cover_note,
            'delala'     => $this->whenLoaded('delala', fn () => [
                'id'        => (string) $this->delala->id,
                'full_name' => $this->delala->full_name,
                'rating'    => $this->delala->rating_average ?? null,
            ]),
            'items'      => $this->whenLoaded('items', fn () =>
                $this->items->map(fn ($item) => [
                    'id'                           => $item->uuid,
                    'item_name'                    => $item->item_name,
                    'description'                  => $item->description,
                    'text_location'                => $item->text_location,
                    'image_urls'                   => collect($item->image_s3_keys ?? [])->map(
                        fn ($k) => \Storage::disk('s3')->temporaryUrl($k, now()->addMinutes(15))
                    ),
                    'estimated_item_price_santim'  => $item->estimated_item_price_santim,
                    'commission_amount_santim'     => $item->commission_amount_santim,
                    'commission_etb'               => $item->commissionInEtb(),
                ])
            ),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
