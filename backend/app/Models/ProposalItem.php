<?php

namespace App\Models;

use App\States\ProposalItem\ProposalItemState;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Spatie\ModelStates\HasStates;

#[Fillable([
    'proposal_id',
    'item_name',
    'description',
    'text_location',
    'image_s3_keys',
    'commission_amount_santim',
    'status',
    'attributes',
])]
class ProposalItem extends Model
{
    use HasStates, HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ProposalItemState::class,
            'image_s3_keys' => 'array',
            'attributes' => 'array',
            'commission_amount_santim' => 'integer',
        ];
    }

    public function proposal(): BelongsTo
    {
        return $this->belongsTo(Proposal::class);
    }

    public function escrowTransaction(): HasOne
    {
        return $this->hasOne(EscrowTransaction::class);
    }

    /**
     * Get commission amount in ETB.
     */
    public function commissionAmountEtb(): float
    {
        return $this->commission_amount_santim / 100;
    }
}
