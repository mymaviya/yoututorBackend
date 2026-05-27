<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PermissionMiddleware
{
    public function handle(
        Request $request,
        Closure $next,
        $permission
    ): Response {

        if (!auth()->check()) {
            return response()->json([
                'message' => 'Unauthenticated'
            ], 401);
        }

        if (!auth()->user()->hasPermission($permission)) {
            return response()->json([
                'message' => 'Permission denied'
            ], 403);
        }

        return $next($request);
    }
}
