<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\AppNotification;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $notifications = AppNotification::where('user_id', auth()->id())
            ->latest()
            ->get()
            ->groupBy(function ($item) {
                return $item->type . '|' . $item->route;
            })
            ->map(function ($group) {
                $latest = $group->first();

                return [
                    'id' => $latest->id,
                    'title' => $latest->title,
                    'message' => $latest->message,
                    'type' => $latest->type,
                    'url' => $latest->route,
                    'route' => $latest->route,
                    'is_read' => $group->every(fn($n) => $n->is_read),
                    'count' => $group->count(),
                    'created_at' => $latest->created_at,
                    'ids' => $group->pluck('id')->values(),
                ];
            })
            ->values();

        return response()->json($notifications);
    }

    public function unreadCount()
    {
        return response()->json([
            'auth_user' => auth()->id(),
            'count' => AppNotification::where('user_id', auth()->id())
                ->where('is_read', false)
                ->count(),
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

    public function markGroupRead(Request $request)
    {
        $data = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:app_notifications,id',
        ]);

        AppNotification::where('user_id', auth()->id())
            ->whereIn('id', $data['ids'])
            ->update(['is_read' => true]);

        return response()->json([
            'message' => 'Notifications marked as read',
        ]);
    }
}
