<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AcademicPlanning\TeacherTimetableService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TeacherTimetableController extends Controller
{
    public function __construct(
        protected TeacherTimetableService $service
    ) {}

    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'mode' => ['nullable', 'in:teacher,class'],
            'academic_year_id' => ['nullable', 'exists:academic_years,id'],
            'teacher_id' => ['nullable', 'exists:users,id'],
            'grade_id' => ['nullable', 'exists:grades,id'],
            'section_id' => ['nullable', 'exists:sections,id'],
            'stream_id' => ['nullable', 'exists:streams,id'],
        ]);

        $mode = $data['mode'] ?? 'teacher';

        if ($mode === 'teacher') {
            abort_if(empty($data['teacher_id']), 422, 'Teacher is required.');

            $result = $this->service->teacherTimetable(
                (int) $data['teacher_id'],
                isset($data['academic_year_id']) ? (int) $data['academic_year_id'] : null,
                $this->subscriptionId()
            );
        } else {
            abort_if(empty($data['grade_id']), 422, 'Grade is required.');

            $result = $this->service->classTimetable(
                (int) $data['grade_id'],
                isset($data['section_id']) ? (int) $data['section_id'] : null,
                isset($data['stream_id']) ? (int) $data['stream_id'] : null,
                isset($data['academic_year_id']) ? (int) $data['academic_year_id'] : null,
                $this->subscriptionId()
            );
        }

        return response()->json([
            'success' => true,
            'data' => $result,
        ]);
    }

    public function teacher(Request $request, int $teacher): JsonResponse
    {
        $data = $request->validate([
            'academic_year_id' => ['nullable', 'exists:academic_years,id'],
        ]);

        return response()->json([
            'success' => true,
            'data' => $this->service->teacherTimetable(
                $teacher,
                isset($data['academic_year_id']) ? (int) $data['academic_year_id'] : null,
                $this->subscriptionId()
            ),
        ]);
    }

    public function classTimetable(Request $request): JsonResponse
    {
        $data = $request->validate([
            'academic_year_id' => ['nullable', 'exists:academic_years,id'],
            'grade_id' => ['required', 'exists:grades,id'],
            'section_id' => ['nullable', 'exists:sections,id'],
            'stream_id' => ['nullable', 'exists:streams,id'],
        ]);

        return response()->json([
            'success' => true,
            'data' => $this->service->classTimetable(
                (int) $data['grade_id'],
                isset($data['section_id']) ? (int) $data['section_id'] : null,
                isset($data['stream_id']) ? (int) $data['stream_id'] : null,
                isset($data['academic_year_id']) ? (int) $data['academic_year_id'] : null,
                $this->subscriptionId()
            ),
        ]);
    }

    public function today(Request $request): JsonResponse
    {
        $data = $request->validate([
            'academic_year_id' => ['nullable', 'exists:academic_years,id'],
            'teacher_id' => ['nullable', 'exists:users,id'],
            'grade_id' => ['nullable', 'exists:grades,id'],
            'section_id' => ['nullable', 'exists:sections,id'],
            'stream_id' => ['nullable', 'exists:streams,id'],
        ]);

        return response()->json([
            'success' => true,
            'data' => $this->service->today(
                isset($data['teacher_id']) ? (int) $data['teacher_id'] : null,
                isset($data['grade_id']) ? (int) $data['grade_id'] : null,
                isset($data['section_id']) ? (int) $data['section_id'] : null,
                isset($data['stream_id']) ? (int) $data['stream_id'] : null,
                isset($data['academic_year_id']) ? (int) $data['academic_year_id'] : null,
                $this->subscriptionId()
            ),
        ]);
    }

    public function freePeriods(Request $request): JsonResponse
    {
        $data = $request->validate([
            'teacher_id' => ['required', 'exists:users,id'],
            'academic_year_id' => ['nullable', 'exists:academic_years,id'],
        ]);

        return response()->json([
            'success' => true,
            'data' => $this->service->freePeriods(
                (int) $data['teacher_id'],
                isset($data['academic_year_id']) ? (int) $data['academic_year_id'] : null,
                $this->subscriptionId()
            ),
        ]);
    }

    public function workload(Request $request): JsonResponse
    {
        $data = $request->validate([
            'teacher_id' => ['required', 'exists:users,id'],
            'academic_year_id' => ['nullable', 'exists:academic_years,id'],
        ]);

        return response()->json([
            'success' => true,
            'data' => $this->service->workload(
                (int) $data['teacher_id'],
                isset($data['academic_year_id']) ? (int) $data['academic_year_id'] : null,
                $this->subscriptionId()
            ),
        ]);
    }

    public function print(Request $request): JsonResponse
    {
        return $this->index($request);
    }

    public function export(Request $request): JsonResponse
    {
        return $this->index($request);
    }

    private function subscriptionId(): ?int
    {
        return auth()->user()?->subscription_id
            ?? auth()->user()?->subscription?->id;
    }
}