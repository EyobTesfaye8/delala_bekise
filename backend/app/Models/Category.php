<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

#[Fillable([
    'parent_id',
    'name',
    'slug',
    'description',
    'attributes_schema',
    'is_active',
    'sort_order',
])]
class Category extends Model
{
    use HasFactory;
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'attributes_schema' => 'array',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id');
    }

    public function jobs(): HasMany
    {
        return $this->hasMany(DelalaJob::class);
    }
}
