<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable([
    'proposal_id', 'item_name', 'description', 'text_location',
    'image_s3_keys', 'estimated_item_price_santim', 'commission_amount_santim',
    'status', 'attributes',
])]
class ProposalItem extends Model
{
    protected static function booted(): void
    {
        static::creating(function (self $item) {
            $item->uuid = (string) Str::orderedUuid();
        });
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'image_s3_keys' => 'array',
            'attributes'    => 'array',
        ];
    }

    public function proposal(): BelongsTo
    {
        return $this->belongsTo(Proposal::class);
    }

    public function priceHistories(): HasMany
    {
        return $this->hasMany(ProposalItemPriceHistory::class);
    }

    public function escrowTransactions(): HasMany
    {
        return $this->hasMany(EscrowTransaction::class);
    }

    /**
     * Commission in ETB (float for display — NOT for arithmetic).
     */
    public function commissionInEtb(): float
    {
        return $this->commission_amount_santim / 100;
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
