<?php

namespace App\Models;

use App\States\Job\JobState;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Spatie\ModelStates\HasStates;

#[Fillable([
    'buyer_id',
    'category_id',
    'title',
    'description',
    'status',
    'location',
    'budget_min',
    'budget_max',
    'attributes',
    'proposal_count',
])]
class DelalaJob extends Model
{
    use HasStates, SoftDeletes, HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => JobState::class,
            'budget_min' => 'decimal:2',
            'budget_max' => 'decimal:2',
            'attributes' => 'array',
            'proposal_count' => 'integer',
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

    public function activeEscrow()
    {
        return $this->hasOne(EscrowTransaction::class, 'job_id')
            ->whereNotIn('status', ['force_refunded', 'refund_failed']);
    }
}
