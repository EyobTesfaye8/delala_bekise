<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Http\Request;

/**
 * Append-only audit trail service.
 * SRS NFR-AUD-1: captures every state mutation on sensitive entities.
 */
class AuditLogService
{
    public function log(
        string $event,
        string $auditableType,
        int $auditableId,
        array $oldValues = [],
        array $newValues = [],
        ?Request $request = null,
    ): AuditLog {
        $actor = $request?->user();

        return AuditLog::create([
            'actor_id'        => $actor?->id,
            'actor_role'      => $actor?->type,
            'ip_address'      => $request?->ip(),
            'user_agent'      => $request?->userAgent(),
            'auditable_type'  => $auditableType,
            'auditable_id'    => $auditableId,
            'event'           => $event,
            'old_values'      => empty($oldValues) ? null : $oldValues,
            'new_values'      => empty($newValues) ? null : $newValues,
            'x_request_id'   => $request?->header('X-Request-ID'),
        ]);
    }
}
