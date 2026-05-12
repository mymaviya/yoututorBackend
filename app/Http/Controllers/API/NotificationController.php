<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\AppNotification;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index()
    {
        return AppNotification::where('user_id', auth()->id())
            ->latest()
            ->paginate(20);
    }

    public function unreadCount()
    {
        return response()->json([
            'count' => AppNotification::where('user_id', auth()->id())
                ->where('is_read', false)
                ->count()
        ]);
    }

    public function markAsRead($id)
    {
        $notification = AppNotification::where('user_id', auth()->id())
            ->findOrFail($id);

        $notification->update([
            'is_read' => true
        ]);

        return response()->json([
            'message' => 'Notification marked as read'
        ]);
    }

    public function markAllAsRead()
    {
        AppNotification::where('user_id', auth()->id())
            ->where('is_read', false)
            ->update([
                'is_read' => true
            ]);

        return response()->json([
            'message' => 'All notifications marked as read'
        ]);
    }
}
