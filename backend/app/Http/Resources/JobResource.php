<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class JobResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->uuid,
            'title'            => $this->title,
            'description'      => $this->description,
            'status'           => (string) $this->status,
            'category'         => $this->whenLoaded('category', fn () => [
                'id'   => $this->category->id,
                'name' => $this->category->name,
            ]),
            'buyer'            => $this->whenLoaded('buyer', fn () => [
                'id'        => $this->buyer->guest_uuid ?? (string) $this->buyer->id,
                'full_name' => $this->buyer->full_name,
            ]),
            'location'         => $this->location,
            'location_region'  => $this->location_region,
            'location_subcity' => $this->location_subcity,
            'budget_min'       => $this->budget_min,
            'budget_max'       => $this->budget_max,
            'attributes'       => $this->attributes,
            'proposal_count'   => $this->proposal_count,
            'created_at'       => $this->created_at?->toIso8601String(),
        ];
    }
}
