<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'action_type',
    'entity_type',
    'entity_id',
    'amount_santim',
    'payload',
    'requested_by_id',
    'approved_by_id',
    'status',
    'justification_note',
    'rejection_reason',
    'approved_at',
])]
class AdminApprovalRequest extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount_santim' => 'integer',
            'payload' => 'array',
            'approved_at' => 'datetime',
        ];
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_id');
    }

    public function isPending(): bool
    {
        return $this->status === 'PENDING';
    }
}
