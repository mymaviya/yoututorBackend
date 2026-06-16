<?php

namespace App\Http\Middleware;

use App\Models\Subscription;
use Closure;
use Illuminate\Http\Request;

class CheckActiveSubscription
{
    public function handle(Request $request, Closure $next)
    {
        $subscription = Subscription::whereIn('status', ['trial', 'active'])
            ->whereDate('starts_at', '<=', now())
            ->whereDate('ends_at', '>=', now())
            ->latest()
            ->first();

        $subscription = Subscription::where('status', 'active')
            ->orWhere('status', 'trial')
            ->latest()
            ->first();
            
        $userRole = auth()->user()->roleData?->slug
            ?? auth()->user()->role;

        if (in_array($userRole, ['superadmin'])) {
            return $next($request);
        }

        if (!$subscription) {
            return response()->json([
                'message' => 'Subscription expired.'
            ], 403);
        }

        if (!$subscription) {
            return response()->json([
                'success' => false,
                'message' => 'Your subscription or demo period has expired. Please renew your plan.',
                'subscription_expired' => true,
            ], 403);
        }

        if (
            $subscription->ends_at &&
            now()->gt($subscription->ends_at)
        ) {
            return response()->json([
                'message' => 'Subscription expired.'
            ], 403);
        }

        return $next($request);
    }
}
