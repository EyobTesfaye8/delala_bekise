<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'transaction_ref', 'ledger_account_id', 'escrow_transaction_id',
    'direction', 'amount_santim', 'description', 'metadata',
])]
class LedgerEntry extends Model
{
    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(LedgerAccount::class, 'ledger_account_id');
    }

    public function escrowTransaction(): BelongsTo
    {
        return $this->belongsTo(EscrowTransaction::class);
    }

    public function isDebit(): bool
    {
        return $this->direction === 'debit';
    }

    public function isCredit(): bool
    {
        return $this->direction === 'credit';
    }
}
