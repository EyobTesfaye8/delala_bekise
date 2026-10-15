<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Immutable audit trail model.
 * SRS NFR-AUD-1 — never updates or deletes records.
 */
class AuditLog extends Model
{
    public const UPDATED_AT = null; // no updated_at column

    protected $fillable = [
        'actor_id',
        'actor_role',
        'ip_address',
        'user_agent',
        'auditable_type',
        'auditable_id',
        'event',
        'old_values',
        'new_values',
        'x_request_id',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
        ];
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
