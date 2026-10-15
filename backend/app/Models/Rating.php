<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Double-blind mutual rating system.
 * SRS FR-6.1: revealed only when both submit, or after 14 days.
 */
#[Fillable([
    'job_id', 'rater_id', 'delala_id', 'stars', 'review_text', 'revealed_at',
])]
class Rating extends Model
{
    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'stars' => 'integer',
            'revealed_at' => 'datetime',
        ];
    }

    public function job(): BelongsTo
    {
        return $this->belongsTo(DelalaJob::class, 'job_id');
    }

    public function rater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rater_id');
    }

    public function delala(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delala_id');
    }

    public function isRevealed(): bool
    {
        return ! is_null($this->revealed_at);
    }
}
