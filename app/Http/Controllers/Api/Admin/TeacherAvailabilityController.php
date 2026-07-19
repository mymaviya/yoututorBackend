<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTeacherAvailabilityRequest;
use App\Http\Requests\UpdateTeacherAvailabilityRequest;
use App\Models\SchoolBell;
use App\Models\TeacherAvailability;
use App\Models\User;
use App\Services\TeacherAvailability\TeacherAvailabilityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TeacherAvailabilityController extends Controller
{
    public function __construct(
        protected TeacherAvailabilityService $service
    ) {}

    /**
     * Get a teacher's weekly availability.
     */
    public function index(Request $request): JsonResponse
    {
        $subscriptionId = (int) $request->user()->subscription_id;

        $validated = $request->validate([
            'teacher_id' => $this->teacherRules($subscriptionId),
            'academic_year_id' => $this->academicYearRules($subscriptionId),
        ]);

        return response()->json([
            'success' => true,
            'data' => $this->service->getWeeklyAvailability(
                $subscriptionId,
                (int) $validated['academic_year_id'],
                (int) $validated['teacher_id']
            ),
        ]);
    }

    /**
     * Return the data needed by the weekly availability bulk editor.
     */
    public function bulkEditorData(Request $request): JsonResponse
    {
        $subscriptionId = (int) $request->user()->subscription_id;

        $teachers = User::query()
            ->where('subscription_id', $subscriptionId)
            ->where('role', 'teacher')
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        $bells = SchoolBell::query()
            ->active()
            ->teachingPeriods()
            ->whereNotNull('period_number')
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
                'weekdays' => [
                    ['value' => 1, 'label' => 'Monday'],
                    ['value' => 2, 'label' => 'Tuesday'],
                    ['value' => 3, 'label' => 'Wednesday'],
                    ['value' => 4, 'label' => 'Thursday'],
                    ['value' => 5, 'label' => 'Friday'],
                    ['value' => 6, 'label' => 'Saturday'],
                    ['value' => 7, 'label' => 'Sunday'],
                ],
                'statuses' => [
                    ['value' => 'available', 'label' => 'Available'],
                    ['value' => 'preferred', 'label' => 'Preferred'],
                    ['value' => 'unavailable', 'label' => 'Unavailable'],
                ],
            ],
        ]);
    }

    /**
     * Store a teacher's complete weekly availability grid.
     */
    public function store(
        StoreTeacherAvailabilityRequest $request
    ): JsonResponse {
        $validated = $request->validated();
        $subscriptionId = (int) $request->user()->subscription_id;

        $availability = $this->service->saveWeeklyAvailability(
            $subscriptionId,
            (int) $validated['academic_year_id'],
            (int) $validated['teacher_id'],
            $validated['availability']
        );

        return response()->json([
            'success' => true,
            'message' => 'Teacher availability saved successfully.',
            'data' => $availability,
        ]);
    }

    /**
     * Update a teacher's complete weekly availability grid.
     */
    public function update(
        UpdateTeacherAvailabilityRequest $request,
        int $teacherId
    ): JsonResponse {
        $subscriptionId = (int) $request->user()->subscription_id;

        validator(
            ['teacher_id' => $teacherId],
            ['teacher_id' => $this->teacherRules($subscriptionId)]
        )->validate();

        $validated = $request->validated();

        $availability = $this->service->saveWeeklyAvailability(
            $subscriptionId,
            (int) $validated['academic_year_id'],
            $teacherId,
            $validated['availability']
        );

        return response()->json([
            'success' => true,
            'message' => 'Teacher availability updated successfully.',
            'data' => $availability,
        ]);
    }

    /**
     * Copy one teacher's availability to another teacher.
     */
    public function copy(Request $request): JsonResponse
    {
        $subscriptionId = (int) $request->user()->subscription_id;

        $validated = $request->validate([
            'academic_year_id' => $this->academicYearRules($subscriptionId),
            'source_teacher_id' => $this->teacherRules($subscriptionId),
            'destination_teacher_id' => [
                ...$this->teacherRules($subscriptionId),
                'different:source_teacher_id',
            ],
        ]);

        $availability = $this->service->copyAvailability(
            $subscriptionId,
            (int) $validated['academic_year_id'],
            (int) $validated['source_teacher_id'],
            (int) $validated['destination_teacher_id']
        );

        return response()->json([
            'success' => true,
            'message' => 'Availability copied successfully.',
            'data' => $availability,
        ]);
    }

    /**
     * Create default availability for all requested teaching slots.
     */
    public function generateDefault(Request $request): JsonResponse
    {
        $subscriptionId = (int) $request->user()->subscription_id;

        $validated = $request->validate([
            'teacher_id' => $this->teacherRules($subscriptionId),
            'academic_year_id' => $this->academicYearRules($subscriptionId),
            'working_days' => ['required', 'integer', 'between:1,7'],
            'periods_per_day' => ['required', 'integer', 'between:1,20'],
        ]);

        $availability = $this->service->createDefaultAvailability(
            $subscriptionId,
            (int) $validated['academic_year_id'],
            (int) $validated['teacher_id'],
            (int) $validated['working_days'],
            (int) $validated['periods_per_day']
        );

        return response()->json([
            'success' => true,
            'message' => 'Default availability created successfully.',
            'data' => $availability,
        ]);
    }

    /**
     * Delete a teacher's complete weekly availability grid.
     */
    public function destroy(Request $request, int $teacherId): JsonResponse
    {
        $subscriptionId = (int) $request->user()->subscription_id;

        validator(
            ['teacher_id' => $teacherId],
            ['teacher_id' => $this->teacherRules($subscriptionId)]
        )->validate();

        $validated = $request->validate([
            'academic_year_id' => $this->academicYearRules($subscriptionId),
        ]);

        $this->service->resetAvailability(
            $subscriptionId,
            (int) $validated['academic_year_id'],
            $teacherId
        );

        return response()->json([
            'success' => true,
            'message' => 'Teacher availability deleted successfully.',
        ]);
    }

    /**
     * Validate an active teacher inside the authenticated subscription.
     */
    private function teacherRules(int $subscriptionId): array
    {
        return [
            'required',
            'integer',
            Rule::exists('users', 'id')->where(
                fn ($query) => $query
                    ->where('subscription_id', $subscriptionId)
                    ->where('is_active', true)
            ),
        ];
    }

    /**
     * Validate an active academic year inside the authenticated subscription.
     */
    private function academicYearRules(int $subscriptionId): array
    {
        return [
            'required',
            'integer',
            Rule::exists('academic_years', 'id')->where(
                fn ($query) => $query
                    ->where('subscription_id', $subscriptionId)
                    ->where('is_active', true)
            ),
        ];
    }
}
