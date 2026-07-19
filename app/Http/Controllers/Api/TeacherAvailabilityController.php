<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Admin\TeacherAvailabilityController as AdminTeacherAvailabilityController;
use App\Models\SchoolBell;
use App\Models\TeacherAvailability;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Backward-compatible controller for the existing routes/api.php import.
 *
 * The secured implementation lives in the Admin namespace. This controller
 * delegates the CRUD actions to it while preserving the bulk editor endpoint
 * used by the current frontend.
 */
class TeacherAvailabilityController extends AdminTeacherAvailabilityController
{
    public function bulkEditorData(Request $request): JsonResponse
    {
        $subscriptionId = (int) $request->user()->subscription_id;

        $teachers = User::query()
            ->where('subscription_id', $subscriptionId)
            ->where('is_active', true)
            ->where(function ($query): void {
                $query->where('role', 'teacher')
                    ->orWhereHas('roleData', function ($roleQuery): void {
                        $roleQuery->where('slug', 'teacher');
                    });
            })
            ->orderBy('name')
            ->get(['id', 'name']);

        $bells = SchoolBell::query()
            ->active()
            ->teachingPeriods()
            ->ordered()
            ->get([
                'id',
                'title',
                'period_number',
                'start_time',
                'end_time',
            ]);

        $availability = TeacherAvailability::query()
            ->where('subscription_id', $subscriptionId)
            ->where('is_active', true)
            ->with('bell')
            ->ordered()
            ->get()
            ->groupBy('teacher_id');

        return response()->json([
            'success' => true,
            'data' => [
                'teachers' => $teachers,
                'bells' => $bells,
                'availability' => $availability,
                'weekdays' => [1, 2, 3, 4, 5, 6],
            ],
        ]);
    }
}
