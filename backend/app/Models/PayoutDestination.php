<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'delala_id',
    'bank_code',
    'bank_name',
    'account_number',
    'account_holder_name',
    'telebirr_number',
    'is_default',
    'verified_at',
])]
class PayoutDestination extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'verified_at' => 'datetime',
        ];
    }

    public function delala(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delala_id');
    }
}
