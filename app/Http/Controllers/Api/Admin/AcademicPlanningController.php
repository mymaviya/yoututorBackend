<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\AcademicPlanning\DashboardService;
use Illuminate\Http\JsonResponse;

class AcademicPlanningController extends Controller
{
    public function __construct(
        protected DashboardService $dashboardService
    ) {}

    /**
     * Academic Planning Dashboard
     */
    public function dashboard(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->dashboardService->dashboard(),
        ]);
    }

    /**
     * Readiness Summary
     */
    public function readiness(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->dashboardService->readiness(),
        ]);
    }

    /**
     * Dashboard Statistics
     */
    public function statistics(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->dashboardService->statistics(),
        ]);
    }

    /**
     * Dashboard Warnings
     */
    public function warnings(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->dashboardService->warnings(),
        ]);
    }
}