<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTeacherAvailabilityRequest;
use App\Http\Requests\UpdateTeacherAvailabilityRequest;
use App\Services\TeacherAvailability\TeacherAvailabilityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TeacherAvailabilityController extends Controller
{
    public function __construct(
        protected TeacherAvailabilityService $service
    ) {}

    /**
     * Get teacher weekly availability.
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'teacher_id' => ['required', 'integer'],
            'academic_year_id' => ['required', 'integer'],
        ]);

        return response()->json([
            'success' => true,
            'data' => $this->service->getWeeklyAvailability(
                auth()->user()->subscription_id,
                $validated['academic_year_id'],
                $validated['teacher_id']
            ),
        ]);
    }

    /**
     * Store availability.
     */
    public function store(
        StoreTeacherAvailabilityRequest $request
    ): JsonResponse {

        $availability = $this->service->saveWeeklyAvailability(
            auth()->user()->subscription_id,
            $request->academic_year_id,
            $request->teacher_id,
            $request->availability
        );

        return response()->json([
            'success' => true,
            'message' => 'Teacher availability saved successfully.',
            'data' => $availability,
        ]);
    }

    /**
     * Update availability.
     */
    public function update(
        UpdateTeacherAvailabilityRequest $request,
        int $teacherId
    ): JsonResponse {

        $availability = $this->service->saveWeeklyAvailability(
            auth()->user()->subscription_id,
            $request->academic_year_id,
            $teacherId,
            $request->availability
        );

        return response()->json([
            'success' => true,
            'message' => 'Teacher availability updated successfully.',
            'data' => $availability,
        ]);
    }

    /**
     * Copy one teacher availability to another.
     */
    public function copy(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'academic_year_id' => ['required', 'integer'],
            'source_teacher_id' => ['required', 'integer'],
            'destination_teacher_id' => ['required', 'integer'],
        ]);

        $availability = $this->service->copyAvailability(
            auth()->user()->subscription_id,
            $validated['academic_year_id'],
            $validated['source_teacher_id'],
            $validated['destination_teacher_id']
        );

        return response()->json([
            'success' => true,
            'message' => 'Availability copied successfully.',
            'data' => $availability,
        ]);
    }

    /**
     * Create default availability.
     */
    public function generateDefault(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'teacher_id' => ['required', 'integer'],
            'academic_year_id' => ['required', 'integer'],
            'working_days' => ['required', 'integer', 'between:1,7'],
            'periods_per_day' => ['required', 'integer', 'between:1,20'],
        ]);

        $availability = $this->service->createDefaultAvailability(
            auth()->user()->subscription_id,
            $validated['academic_year_id'],
            $validated['teacher_id'],
            $validated['working_days'],
            $validated['periods_per_day']
        );

        return response()->json([
            'success' => true,
            'message' => 'Default availability created successfully.',
            'data' => $availability,
        ]);
    }

    /**
     * Delete teacher availability.
     */
    public function destroy(Request $request, int $teacherId): JsonResponse
    {
        $validated = $request->validate([
            'academic_year_id' => ['required', 'integer'],
        ]);

        $this->service->resetAvailability(
            auth()->user()->subscription_id,
            $validated['academic_year_id'],
            $teacherId
        );

        return response()->json([
            'success' => true,
            'message' => 'Teacher availability deleted successfully.',
        ]);
    }
}