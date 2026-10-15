<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'code', 'name', 'type', 'user_id',
])]
class LedgerAccount extends Model
{
    /** Chart of Account codes per SRS §5.1 */
    public const SYSTEM_CHAPA_HOLDING = '1010';

    public const SYSTEM_PAYOUT_CLEARING = '1020';

    public const BUYER_ESCROW_LIABILITY = '2010';

    public const DELALA_PENDING_WALLET = '2020';

    public const DELALA_AVAILABLE_WALLET = '2030';

    public const PLATFORM_REVENUE_WALLET = '4010';

    public const CHAPA_GATEWAY_EXPENSE = '5010';

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function entries(): HasMany
    {
        return $this->hasMany(LedgerEntry::class);
    }

    /**
     * Current balance in Santim (sum of debits - sum of credits for asset accounts,
     * or sum of credits - sum of debits for liability/equity accounts).
     */
    public function balanceSantim(): int
    {
        $debits = (int) $this->entries()->where('direction', 'debit')->sum('amount_santim');
        $credits = (int) $this->entries()->where('direction', 'credit')->sum('amount_santim');

        return match ($this->type) {
            'asset', 'expense' => $debits - $credits,
            default => $credits - $debits,
        };
    }
}
