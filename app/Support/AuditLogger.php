<?php

namespace App\Support;

use App\Models\AuditLog;
use App\Models\User;

class AuditLogger
{
    public function record(
        ?User $actor,
        string $action,
        string $entity,
        int|string|null $entityId,
        array $oldValues = [],
        array $newValues = [],
        ?string $reason = null,
        array $meta = []
    ): AuditLog {
        return AuditLog::create([
            'user_id' => $actor?->id,
            'action' => $action,
            'entity' => $entity,
            'entity_id' => $entityId,
            'meta' => array_merge($meta, [
                'actor_role' => $actor?->role,
                'old_values' => $oldValues,
                'new_values' => $newValues,
                'reason' => $reason,
            ]),
        ]);
    }
}
