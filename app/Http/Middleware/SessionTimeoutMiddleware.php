<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SessionTimeoutMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        $lastActivity = session('last_activity_time');
        $timeout = (int) ($user->session_timeout_minutes ?? 30);

        if ($timeout <= 0) {
            $timeout = 30;
        }

        if ($lastActivity && now()->diffInMinutes($lastActivity) > $timeout) {
            $user->currentAccessToken()?->delete();

            return response()->json([
                'message' => 'Session expired due to inactivity.',
            ], 401);
        }

        session(['last_activity_time' => now()]);

        return $next($request);
    }
}
