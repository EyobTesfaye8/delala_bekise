<?php

namespace App\Models;

use App\Models\States\ProposalAccepted;
use App\Models\States\ProposalNegotiating;
use App\Models\States\ProposalPending;
use App\Models\States\ProposalRejected;
use App\Models\States\ProposalStatus;
use App\Models\States\ProposalWithdrawn;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Spatie\ModelStates\HasStates;

#[Fillable([
    'job_id', 'delala_id', 'status', 'cover_note',
])]
class Proposal extends Model
{
    use HasStates, SoftDeletes;

    protected static function booted(): void
    {
        static::creating(function (self $proposal) {
            $proposal->uuid = (string) Str::orderedUuid();
        });
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => ProposalStatus::class,
        ];
    }

    public function job(): BelongsTo
    {
        return $this->belongsTo(DelalaJob::class, 'job_id');
    }

    public function delala(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delala_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ProposalItem::class);
    }

    public function chat(): HasOne
    {
        return $this->hasOne(Chat::class);
    }

    // Status helpers
    public function isPending(): bool
    {
        return $this->status instanceof ProposalPending;
    }

    public function isNegotiating(): bool
    {
        return $this->status instanceof ProposalNegotiating;
    }

    public function isAccepted(): bool
    {
        return $this->status instanceof ProposalAccepted;
    }

    public function isRejected(): bool
    {
        return $this->status instanceof ProposalRejected;
    }

    public function isWithdrawn(): bool
    {
        return $this->status instanceof ProposalWithdrawn;
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
