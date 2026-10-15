<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'proposal_item_id', 'changed_by_id', 'old_amount_santim', 'new_amount_santim',
])]
class ProposalItemPriceHistory extends Model
{
    public const UPDATED_AT = null;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [];
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
