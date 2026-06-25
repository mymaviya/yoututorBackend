<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Schema;

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
        $user = auth()->user();

        $payload = [
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
        ];

        if (Schema::hasColumn('audit_logs', 'subscription_id')) {
            $payload['subscription_id'] = $user?->subscription_id;
        }

        return AuditLog::create($payload);
    }
}
