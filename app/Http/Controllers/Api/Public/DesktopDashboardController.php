<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Controller;
use App\Services\DesktopDashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DesktopDashboardController extends Controller
{
    public function data(Request $request, DesktopDashboardService $service): JsonResponse
    {
        return response()->json(
            $service->getData($request)
        );
    }
}
