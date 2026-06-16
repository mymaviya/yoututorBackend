<?php

namespace App\Http\Middleware;

use App\Models\SidebarMenu;
use App\Models\Subscription;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRouteFeature
{
    public function handle(Request $request, Closure $next): Response
    {
        $routeName = $request->route()?->getName();

        if (!$routeName) {
            return $next($request);
        }

        $menu = SidebarMenu::where('route_name', $routeName)
            ->where('is_active', true)
            ->first();

        if (!$menu || empty($menu->feature_key)) {
            return $next($request);
        }

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

        $allowed = $subscription->plan?->featureItems
            ->where('feature_key', $menu->feature_key)
            ->where('is_enabled', true)
            ->isNotEmpty();

        if (!$allowed) {
            return response()->json([
                'success' => false,
                'message' => 'This feature is not available in your current subscription plan.',
                'feature_key' => $menu->feature_key,
            ], 403);
        }

        return $next($request);
    }
}