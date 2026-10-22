<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'escrow_transaction_id',
    'buyer_id',
    'delala_id',
    'rater_id',
    'ratee_id',
    'stars',
    'review',
    'is_revealed',
    'revealed_at',
])]
class Rating extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'stars' => 'integer',
            'is_revealed' => 'boolean',
            'revealed_at' => 'datetime',
        ];
    }

    public function escrowTransaction(): BelongsTo
    {
        return $this->belongsTo(EscrowTransaction::class);
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function delala(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delala_id');
    }

    public function rater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rater_id');
    }

    public function ratee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ratee_id');
    }
}
