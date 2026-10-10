<?php

namespace App\Models;

use App\States\Payout\PayoutState;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\ModelStates\HasStates;

#[Fillable([
    'delala_id',
    'amount_santim',
    'status',
    'bank_name',
    'account_number',
    'telebirr_number',
    'payment_method',
    'processed_by',
    'paid_at',
    'admin_note',
])]
class PayoutRequest extends Model
{
    use HasStates;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => PayoutState::class,
            'amount_santim' => 'integer',
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
}
