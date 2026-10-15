<?php

namespace App\Models;

use App\Models\States\EscrowAutoRefunded;
use App\Models\States\EscrowCancellationRequested;
use App\Models\States\EscrowDisputed;
use App\Models\States\EscrowForceRefunded;
use App\Models\States\EscrowForceReleased;
use App\Models\States\EscrowFunded;
use App\Models\States\EscrowHandoverDeclared;
use App\Models\States\EscrowPendingPayment;
use App\Models\States\EscrowRefundFailed;
use App\Models\States\EscrowReleased;
use App\Models\States\EscrowStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use Spatie\ModelStates\HasStates;

#[Fillable([
    'uuid', 'job_id', 'proposal_item_id', 'buyer_id', 'delala_id',
    'commission_amount_santim', 'platform_fee_santim', 'delala_amount_santim',
    'status', 'chapa_tx_ref', 'chapa_checkout_url', 'chapa_paid_at',
    'handover_declared_at', 'auto_release_at', 'cancellation_requested_at',
    'cancellation_deadline_at', 'dispute_evidence_s3_key', 'dispute_evidence_s3_keys',
    'dispute_reason_code', 'admin_resolution_note', 'resolved_by', 'resolved_at',
    'chapa_webhook_payload', 'overpayment_tx_ref',
])]
class EscrowTransaction extends Model
{
    use HasStates;

    protected static function booted(): void
    {
        static::creating(function (self $escrow) {
            $escrow->uuid = (string) Str::orderedUuid();
        });
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status'                    => EscrowStatus::class,
            'chapa_paid_at'             => 'datetime',
            'handover_declared_at'      => 'datetime',
            'auto_release_at'           => 'datetime',
            'cancellation_requested_at' => 'datetime',
            'cancellation_deadline_at'  => 'datetime',
            'resolved_at'               => 'datetime',
            'chapa_webhook_payload'     => 'array',
            'dispute_evidence_s3_keys'  => 'array',
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

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    // ─────────────────────────── Status helpers ─────────────────────────────

    public function isFunded(): bool
    {
        return $this->status instanceof EscrowFunded;
    }

    public function isPendingPayment(): bool
    {
        return $this->status instanceof EscrowPendingPayment;
    }

    public function isHandoverDeclared(): bool
    {
        return $this->status instanceof EscrowHandoverDeclared;
    }

    public function isDisputed(): bool
    {
        return $this->status instanceof EscrowDisputed;
    }

    public function isReleased(): bool
    {
        return $this->status instanceof EscrowReleased
            || $this->status instanceof EscrowForceReleased;
    }

    public function isRefunded(): bool
    {
        return $this->status instanceof EscrowAutoRefunded
            || $this->status instanceof EscrowForceRefunded;
    }

    public function isRefundFailed(): bool
    {
        return $this->status instanceof EscrowRefundFailed;
    }

    public function isCancellationRequested(): bool
    {
        return $this->status instanceof EscrowCancellationRequested;
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
