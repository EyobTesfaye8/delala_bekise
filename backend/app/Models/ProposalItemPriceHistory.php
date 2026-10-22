<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'proposal_item_id',
    'old_amount_santim',
    'new_amount_santim',
    'changed_by_id',
])]
class ProposalItemPriceHistory extends Model
{
    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'old_amount_santim' => 'integer',
            'new_amount_santim' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (ProposalItemPriceHistory $history) {
            $history->created_at = $history->created_at ?? now();
        });
    }

    public function proposalItem(): BelongsTo
    {
        return $this->belongsTo(ProposalItem::class);
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by_id');
    }
}
