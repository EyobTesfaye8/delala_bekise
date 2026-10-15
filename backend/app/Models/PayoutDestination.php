<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

#[Fillable([
    'delala_id', 'method', 'bank_name', 'account_holder_name',
    'account_number', 'is_verified_by_admin', 'is_default',
])]
class PayoutDestination extends Model
{
    use SoftDeletes;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'is_verified_by_admin' => 'boolean',
            'is_default' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $dest) {
            $dest->uuid = (string) Str::orderedUuid();
        });
    }

    public function delala(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delala_id');
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
