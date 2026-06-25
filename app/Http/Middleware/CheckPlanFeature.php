<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPlanFeature
{
    public function handle(Request $request, Closure $next, string $featureKey): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        if ($this->isSuperAdmin($user)) {
            return $next($request);
        }

        $subscription = $user->subscription?->loadMissing('plan.featureItems');

        if (! $subscription) {
            return response()->json([
                'success' => false,
                'message' => 'No active subscription is assigned to your account.',
                'feature_key' => $featureKey,
            ], 403);
        }

        if (! in_array($subscription->status, ['active', 'trial'], true)) {
            return response()->json([
                'success' => false,
                'message' => 'Your subscription is not active.',
                'feature_key' => $featureKey,
            ], 403);
        }

        if ($subscription->starts_at && now()->lt($subscription->starts_at)) {
            return response()->json([
                'success' => false,
                'message' => 'Your subscription has not started yet.',
                'feature_key' => $featureKey,
            ], 403);
        }

        if ($subscription->ends_at && now()->gt($subscription->ends_at)) {
            return response()->json([
                'success' => false,
                'message' => 'Your subscription has expired.',
                'feature_key' => $featureKey,
            ], 403);
        }

        $hasFeature = $subscription->plan?->featureItems
            ->where('feature_key', $featureKey)
            ->where('is_enabled', true)
            ->isNotEmpty();

        if (! $hasFeature) {
            return response()->json([
                'success' => false,
                'message' => 'This feature is not available in your current subscription plan.',
                'feature_key' => $featureKey,
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
