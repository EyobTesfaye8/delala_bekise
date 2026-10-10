<?php

namespace App\Models;

use App\States\Escrow\EscrowState;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Spatie\ModelStates\HasStates;

#[Fillable([
    'uuid',
    'job_id',
    'proposal_item_id',
    'buyer_id',
    'delala_id',
    'commission_amount_santim',
    'platform_fee_santim',
    'delala_amount_santim',
    'status',
    'chapa_tx_ref',
    'chapa_checkout_url',
    'chapa_paid_at',
    'handover_declared_at',
    'auto_release_at',
    'cancellation_requested_at',
    'cancellation_deadline_at',
    'dispute_evidence_s3_key',
    'admin_resolution_note',
    'resolved_by',
    'resolved_at',
    'chapa_webhook_payload',
])]
class EscrowTransaction extends Model
{
    use HasStates;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => EscrowState::class,
            'commission_amount_santim' => 'integer',
            'platform_fee_santim' => 'integer',
            'delala_amount_santim' => 'integer',
            'chapa_paid_at' => 'datetime',
            'handover_declared_at' => 'datetime',
            'auto_release_at' => 'datetime',
            'cancellation_requested_at' => 'datetime',
            'cancellation_deadline_at' => 'datetime',
            'resolved_at' => 'datetime',
            'chapa_webhook_payload' => 'array',
        ];
    }

    public function job(): BelongsTo
    {
        return $this->belongsTo(DelalaJob::class, 'job_id');
    }

    public function proposalItem(): BelongsTo
    {
        return $this->belongsTo(ProposalItem::class);
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function delala(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delala_id');
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function rating(): HasOne
    {
        return $this->hasOne(Rating::class);
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(LedgerEntry::class);
    }
}
