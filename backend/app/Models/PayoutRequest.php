<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'delala_id', 'amount_santim', 'status', 'bank_name',
    'account_number', 'telebirr_number', 'payment_method',
    'processed_by', 'paid_at', 'admin_note',
    'payout_destination_id', 'batch_ref',
])]
class PayoutRequest extends Model
{
    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'paid_at' => 'datetime',
        ];
    }

    public function delala(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delala_id');
    }

    public function processor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    public function destination(): BelongsTo
    {
        return $this->belongsTo(PayoutDestination::class, 'payout_destination_id');
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }

    /**
     * Minimum withdrawal is 500.00 ETB = 50000 Santim. SRS FR-2.5.
     */
    public static function minimumSantim(): int
    {
        return 50000;
    }
}
