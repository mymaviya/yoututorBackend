<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PermissionMiddleware
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        if ($this->isSuperAdmin($user)) {
            return $next($request);
        }

        if (! $user->hasPermission($permission)) {
            return response()->json([
                'message' => 'Permission denied.',
                'permission' => $permission,
            ], 403);
        }

        return $next($request);
    }

    private function isSuperAdmin($user): bool
    {
        $role = $user?->roleData?->slug ?? $user?->role;

        return in_array($role, ['superadmin', 'super_admin'], true);
    }
}
