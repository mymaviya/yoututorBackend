<?php

namespace App\Http\Middleware;

use App\Models\Subscription;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPlanFeature
{
    public function handle(Request $request, Closure $next, string $featureKey): Response
    {
        $subscription = Subscription::with('plan.featureItems')
            ->whereIn('status', ['active', 'trial'])
            ->whereDate('ends_at', '>=', now())
            ->latest()
            ->first();

        if (!$subscription) {
            return response()->json([
                'success' => false,
                'message' => 'No active subscription found.',
            ], 403);
        }

        $hasFeature = $subscription->plan?->featureItems
            ->where('feature_key', $featureKey)
            ->where('is_enabled', true)
            ->isNotEmpty();

        if (!$hasFeature) {
            return response()->json([
                'success' => false,
                'message' => 'This feature is not available in your current subscription plan.',
                'feature_key' => $featureKey,
            ], 403);
        }

        return $next($request);
    }
}