<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

#[Fillable([
    'initiated_by', 'approved_by', 'type',
    'escrow_transaction_id', 'payout_request_id',
    'amount_santim', 'status', 'justification_note', 'senior_note', 'resolved_at',
])]
class AdminApprovalRequest extends Model
{
    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'resolved_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $req) {
            $req->uuid = (string) Str::orderedUuid();
        });
    }

    public function initiator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiated_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function escrowTransaction(): BelongsTo
    {
        return $this->belongsTo(EscrowTransaction::class);
    }

    public function payoutRequest(): BelongsTo
    {
        return $this->belongsTo(PayoutRequest::class);
    }

    public function isPending(): bool
    {
        return $this->status === 'pending_senior_approval';
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
