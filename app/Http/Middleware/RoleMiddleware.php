<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, ...$roles)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'message' => 'Unauthenticated.'
            ], 401);
        }

        $user->loadMissing('roleData');

        $userRoleSlug = $user->roleData?->slug
            ?? $user->role;

        $userRoleSlug = strtolower(trim($userRoleSlug));

        $allowedRoles = array_map(
            fn($role) => strtolower(trim($role)),
            $roles
        );

        if (!in_array($userRoleSlug, $allowedRoles)) {
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
