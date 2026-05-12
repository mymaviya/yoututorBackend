<?php

use App\Models\AppNotification;

if (!function_exists('notifyUser')) {
    function notifyUser($userId, $title, $message = null, $type = 'general', $url = null)
    {
        return AppNotification::create([
            'user_id' => $userId,
            'title' => $title,
            'message' => $message,
            'type' => $type,
            'url' => $url,
        ]);
    }
}
