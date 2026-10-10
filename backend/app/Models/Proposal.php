<?php

namespace App\Models;

use App\States\Proposal\ProposalState;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Spatie\ModelStates\HasStates;

#[Fillable([
    'job_id',
    'delala_id',
    'status',
    'cover_note',
])]
class Proposal extends Model
{
    use HasStates, SoftDeletes, HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ProposalState::class,
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
}
