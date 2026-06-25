<?php

namespace App\Services;

use App\Models\LoginHoliday;
use App\Models\UserDevice;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class LoginSecurityService
{
    public function check(Request $request, $user): ?string
    {
        if (! $user->holiday_login_allowed) {
            $isHoliday = LoginHoliday::whereDate(
                'date',
                Carbon::now('Asia/Kolkata')->toDateString()
            )
                ->where('is_active', true)
                ->exists();

            if ($isHoliday) {
                return 'Login is restricted today due to holiday.';
            }
        }

        if (! empty($user->allowed_ips)) {
            $allowedIps = is_array($user->allowed_ips)
                ? $user->allowed_ips
                : json_decode($user->allowed_ips, true);

            if ($allowedIps && ! in_array($request->ip(), $allowedIps, true)) {
                return 'Login is not allowed from this IP address.';
            }
        }

        if ($user->device_lock_enabled) {
            $deviceId = $this->deviceId($request);

            $trusted = UserDevice::where('user_id', $user->id)
                ->where('device_id', $deviceId)
                ->where('is_trusted', true)
                ->exists();

            $deviceCount = UserDevice::where('user_id', $user->id)
                ->where('is_trusted', true)
                ->count();

            if (! $trusted && $deviceCount >= (int) $user->max_devices) {
                return 'Login blocked. Maximum trusted devices reached.';
            }
        }

        return null;
    }

    public function registerDevice(Request $request, $user): void
    {
        if (app()->environment('local')) {
            return;
        }

        // Superadmin/admin can login from any device.
        if (in_array(($user->role ?? null), ['superadmin', 'super_admin', 'admin'], true)) {
            return;
        }

        $deviceId = $this->deviceId($request);

        $existingDeviceQuery = UserDevice::where('device_id', $deviceId);

        if (Schema::hasColumn('user_devices', 'subscription_id')) {
            $existingDeviceQuery->where('subscription_id', $user->subscription_id);
        }

        $existingDevice = $existingDeviceQuery->first();

        if ($existingDevice && (int) $existingDevice->user_id !== (int) $user->id) {
            throw new \Exception(
                'This computer is already assigned to another user. Only one account is allowed per device.'
            );
        }

        $values = [
            'device_name' => $request->header('X-Device-Name'),
            'browser' => $request->header('X-Browser'),
            'platform' => $request->header('X-Platform'),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'last_used_at' => now(),
            'is_trusted' => true,
        ];

        if (Schema::hasColumn('user_devices', 'subscription_id')) {
            $values['subscription_id'] = $user->subscription_id;
        }

        UserDevice::updateOrCreate(
            [
                'user_id' => $user->id,
                'device_id' => $deviceId,
            ],
            $values
        );
    }

    private function deviceId(Request $request): string
    {
        return hash(
            'sha256',
            $request->userAgent() . '|' . $request->ip()
        );
    }
}
