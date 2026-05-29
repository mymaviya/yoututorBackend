<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\UserDevice;
use Illuminate\Http\Request;

class UserDeviceController extends Controller
{
    public function index(Request $request)
    {
        return UserDevice::with('user:id,name,email')
            ->when($request->user_id, fn ($q) => $q->where('user_id', $request->user_id))
            ->latest('last_used_at')
            ->get();
    }

    public function trust(UserDevice $device)
    {
        $device->update(['is_trusted' => true]);

        return response()->json([
            'message' => 'Device trusted successfully',
        ]);
    }

    public function block(UserDevice $device)
    {
        $device->update(['is_trusted' => false]);

        return response()->json([
            'message' => 'Device blocked successfully',
        ]);
    }

    public function destroy(UserDevice $device)
    {
        $device->delete();

        return response()->json([
            'message' => 'Device removed successfully',
        ]);
    }
}
