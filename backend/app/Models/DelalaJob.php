<?php

namespace App\Models;

use App\Models\States\Canceled;
use App\Models\States\Contracted;
use App\Models\States\Draft;
use App\Models\States\JobStatus;
use App\Models\States\Negotiating;
use App\Models\States\Open;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Spatie\ModelStates\HasStates;

#[Fillable([
    'buyer_id', 'category_id', 'title', 'description', 'status',
    'location', 'location_region', 'location_subcity',
    'budget_min', 'budget_max', 'attributes', 'proposal_count',
    'canceled_reason',
])]
class DelalaJob extends Model
{
    use HasStates, SoftDeletes;

    protected $table = 'delala_jobs';

    protected static function booted(): void
    {
        static::creating(function (self $job) {
            $job->uuid = (string) Str::orderedUuid();
        });
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status'     => JobStatus::class,
            'attributes' => 'array',
        ];
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function proposals(): HasMany
    {
        return $this->hasMany(Proposal::class, 'job_id');
    }

    public function escrows(): HasMany
    {
        return $this->hasMany(EscrowTransaction::class, 'job_id');
    }

    // ─────────────────────────── Status helpers ─────────────────────────────

    public function isDraft(): bool
    {
        return $this->status instanceof Draft;
    }

    public function isOpen(): bool
    {
        return $this->status instanceof Open;
    }

    public function isNegotiating(): bool
    {
        return $this->status instanceof Negotiating;
    }

    public function isContracted(): bool
    {
        return $this->status instanceof Contracted;
    }

    public function isCanceled(): bool
    {
        return $this->status instanceof Canceled;
    }

    /**
     * Route model binding key.
     */
    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
