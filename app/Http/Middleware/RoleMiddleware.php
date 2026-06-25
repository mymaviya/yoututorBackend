<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $user->loadMissing('roleData');

        $userRoleSlug = strtolower(trim($user->roleData?->slug ?? $user->role));

        if (in_array($userRoleSlug, ['superadmin', 'super_admin'], true)) {
            return $next($request);
        }

        $allowedRoles = array_map(
            fn ($role) => strtolower(trim($role)),
            $roles
        );

        if (! in_array($userRoleSlug, $allowedRoles, true)) {
            return response()->json([
                'message' => 'Unauthorized.',
                'user_role' => $userRoleSlug,
                'allowed_roles' => $allowedRoles,
                'role_id' => $user->role_id,
            ], 403);
        }

        return $next($request);
    }
}
