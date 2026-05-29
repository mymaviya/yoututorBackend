<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SessionTimeoutMiddleware
{
    public function handle($request, Closure $next)
    {
        $user = auth()->user();

        if (!$user) {
            return $next($request);
        }

        $lastActivity = session('last_activity_time');

        $timeout = $user->session_timeout_minutes ?? 30;

        if ($lastActivity && now()->diffInMinutes($lastActivity) > $timeout) {
            auth()->user()->currentAccessToken()?->delete();

            return response()->json([
                'message' => 'Session expired due to inactivity.'
            ], 401);
        }

        session(['last_activity_time' => now()]);

        return $next($request);
    }
}
