<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckActiveSubscription
{
    public function handle(Request $request, Closure $next): Response
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

        $subscription = $user->subscription?->loadMissing('licenseKey');

        if (! $subscription) {
            return response()->json([
                'success' => false,
                'message' => 'No active subscription is assigned to your account.',
                'subscription_expired' => true,
            ], 403);
        }

        if (! in_array($subscription->status, ['trial', 'active'], true)) {
            return response()->json([
                'success' => false,
                'message' => 'Your subscription is not active. Please contact support.',
                'subscription_expired' => true,
            ], 403);
        }

        if ($subscription->starts_at && now()->lt($subscription->starts_at)) {
            return response()->json([
                'success' => false,
                'message' => 'Your subscription has not started yet.',
                'subscription_expired' => true,
            ], 403);
        }

        if ($subscription->ends_at && now()->gt($subscription->ends_at)) {
            return response()->json([
                'success' => false,
                'message' => 'Your subscription or demo period has expired. Please renew your plan.',
                'subscription_expired' => true,
            ], 403);
        }

        $license = $subscription->licenseKey;

        if (! $license || $license->status !== 'active') {
            return response()->json([
                'success' => false,
                'message' => 'Your license key is inactive or missing.',
                'subscription_expired' => true,
            ], 403);
        }

        if ($license->expires_at && now()->gt($license->expires_at)) {
            return response()->json([
                'success' => false,
                'message' => 'Your license key has expired.',
                'subscription_expired' => true,
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
