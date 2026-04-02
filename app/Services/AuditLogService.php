<?php

namespace App\Services;

use App\Models\AuditLog;

class AuditLogService
{
    public function log(
        string $orderId,
        string $action,
        ?string $reason = null,
        ?array $metadata = null,
        string $actor = 'system',
    ): AuditLog {
        return AuditLog::create([
            'order_id' => $orderId,
            'action' => $action,
            'actor' => $actor,
            'reason' => $reason,
            'metadata' => $metadata,
        ]);
    }
}
