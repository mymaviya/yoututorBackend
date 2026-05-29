<?php

namespace App\Services;

use App\Models\AuditLog;

class AuditService
{
    public static function log(
    $module,
    $action,
    $description = null,
    $oldValues = null,
    $newValues = null,
    $userId = null
) {
    $request = request();

    AuditLog::create([
        'user_id' => $userId ?? auth()->id(),
        'module' => $module,
        'action' => $action,
        'description' => $description,
        'ip_address' => $request->ip(),
        'browser' => $request->header('X-Browser'),
        'platform' => $request->header('X-Platform'),
        'user_agent' => $request->userAgent(),
        'old_values' => $oldValues,
        'new_values' => $newValues,
    ]);
}
}
