<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        return AuditLog::with('user:id,name,email,profile')

            ->when($request->module,
                fn ($q) => $q->where('module', $request->module)
            )

            ->when($request->action,
                fn ($q) => $q->where('action', $request->action)
            )

            ->latest()

            ->paginate(50);
    }
}
