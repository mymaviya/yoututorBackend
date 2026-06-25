<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserDevice;
use Illuminate\Http\Request;

class UserDeviceController extends Controller
{
    private function isSuperAdmin(): bool
    {
        $user = auth()->user();
        $role = $user?->roleData?->slug ?? $user?->role;

        return in_array($role, ['superadmin', 'super_admin'], true);
    }

    private function tenantId(): ?int
    {
        return auth()->user()?->subscription_id;
    }

    private function ensureDeviceAccess(UserDevice $device)
    {
        if ($this->isSuperAdmin()) {
            return null;
        }

        $device->loadMissing('user');

        if ((int) $device->user?->subscription_id !== (int) $this->tenantId()) {
            return response()->json([
                'message' => 'You are not allowed to access this device.',
            ], 403);
        }

        return null;
    }

    public function index(Request $request)
    {
        $query = UserDevice::with('user:id,name,email,subscription_id');

        if (! $this->isSuperAdmin()) {
            $query->whereHas('user', function ($q) {
                $q->where('subscription_id', $this->tenantId());
            });
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        return $query
            ->latest('last_used_at')
            ->get();
    }

    public function trust(UserDevice $device)
    {
        if ($response = $this->ensureDeviceAccess($device)) {
            return $response;
        }

        $device->update(['is_trusted' => true]);

        return response()->json([
            'message' => 'Device trusted successfully',
        ]);
    }

    public function block(UserDevice $device)
    {
        if ($response = $this->ensureDeviceAccess($device)) {
            return $response;
        }

        $device->update(['is_trusted' => false]);

        return response()->json([
            'message' => 'Device blocked successfully',
        ]);
    }

    public function destroy(UserDevice $device)
    {
        if ($response = $this->ensureDeviceAccess($device)) {
            return $response;
        }

        $device->delete();

        return response()->json([
            'message' => 'Device removed successfully',
        ]);
    }
}
