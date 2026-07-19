<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\AcademicPlanning\DashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AcademicPlanningController extends Controller
{
    public function __construct(
        protected DashboardService $dashboardService
    ) {}

    public function dashboard(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->dashboardService->dashboard(
                $this->subscriptionId($request)
            ),
        ]);
    }

    public function readiness(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->dashboardService->readiness(
                $this->subscriptionId($request)
            ),
        ]);
    }

    public function statistics(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->dashboardService->statistics(
                $this->subscriptionId($request)
            ),
        ]);
    }

    public function warnings(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->dashboardService->warnings(
                $this->subscriptionId($request)
            ),
        ]);
    }

    private function subscriptionId(Request $request): int
    {
        $subscriptionId = $request->user()?->subscription_id
            ?? $request->user()?->subscription?->id;

        abort_if(
            ! is_numeric($subscriptionId) || (int) $subscriptionId <= 0,
            403,
            'A valid subscription is required.'
        );

        return (int) $subscriptionId;
    }
}
