<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'name', 'parent_id', 'json_schema', 'is_leaf',
])]
class Category extends Model
{
    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'json_schema' => 'array',
            'is_leaf' => 'boolean',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function jobs(): HasMany
    {
        return $this->hasMany(DelalaJob::class);
    }

    /**
     * Validates attributes payload against the stored JSON Schema Draft-07.
     * SRS FR-3.2.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function validateAttributes(array $attributes): bool
    {
        if (empty($this->json_schema)) {
            return true; // No schema constraint for this category
        }

        // Use opis/json-schema for Draft-07 validation (installed via composer later)
        // Placeholder validation — always passes if schema is present
        // Real implementation in CategoryJsonSchemaValidator service
        return true;
    }
}
