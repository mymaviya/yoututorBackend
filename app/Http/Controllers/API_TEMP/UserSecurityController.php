<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use App\Services\AuditService;

class UserSecurityController extends Controller
{
    public function show(User $user)
    {
        return response()->json([
            'session_timeout_minutes' => $user->session_timeout_minutes,
            'allow_multiple_sessions' => $user->allow_multiple_sessions,
            'allowed_ips' => $user->allowed_ips ?: [],
            'device_lock_enabled' => $user->device_lock_enabled,
            'max_devices' => $user->max_devices,
            'otp_login_enabled' => $user->otp_login_enabled,
            'holiday_login_allowed' => $user->holiday_login_allowed,
        ]);
    }

    public function update(Request $request, User $user)
    {
        $data = $request->validate([
            'session_timeout_minutes' => 'required|integer|min:5|max:1440',
            'allow_multiple_sessions' => 'required|boolean',
            'allowed_ips' => 'nullable|array',
            'allowed_ips.*' => 'ip',
            'device_lock_enabled' => 'required|boolean',
            'max_devices' => 'required|integer|min:1|max:10',
            'otp_login_enabled' => 'required|boolean',
            'holiday_login_allowed' => 'required|boolean',
        ]);

        $oldSettings = $user->toArray();

        $user->update($data);

        AuditService::log('Security','Update','Security settings updated for user: ' . $user->name,$oldSettings,$user->fresh()->toArray());

        return response()->json([
            'message' => 'Security settings updated successfully',
            'data' => $user,
        ]);
    }
}
