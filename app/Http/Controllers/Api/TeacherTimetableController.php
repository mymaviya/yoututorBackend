<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AcademicPlanning\TeacherTimetableService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TeacherTimetableController extends Controller
{
    public function __construct(
        protected TeacherTimetableService $service
    ) {}

    public function index(Request $request): JsonResponse
    {
        $subscriptionId = $this->subscriptionId();

        $data = $request->validate([
            'mode' => ['nullable', Rule::in(['teacher', 'class'])],
            'academic_year_id' => [
                'nullable',
                'integer',
                Rule::exists('academic_years', 'id')
                    ->where('subscription_id', $subscriptionId),
            ],
            'teacher_id' => [
                Rule::requiredIf(
                    fn () => $request->input('mode', 'teacher') === 'teacher'
                ),
                'nullable',
                'integer',
                Rule::exists('users', 'id')
                    ->where('subscription_id', $subscriptionId),
            ],
            'grade_id' => [
                Rule::requiredIf(
                    fn () => $request->input('mode', 'teacher') === 'class'
                ),
                'nullable',
                'integer',
                Rule::exists('grades', 'id')
                    ->where('subscription_id', $subscriptionId),
            ],
            'section_id' => [
                'nullable',
                'integer',
                Rule::exists('sections', 'id')
                    ->where('subscription_id', $subscriptionId),
            ],
            'stream_id' => [
                'nullable',
                'integer',
                Rule::exists('streams', 'id')
                    ->where('subscription_id', $subscriptionId),
            ],
        ]);

        $mode = $data['mode'] ?? 'teacher';

        $result = $mode === 'teacher'
            ? $this->service->teacherTimetable(
                teacherId: (int) $data['teacher_id'],
                academicYearId: $data['academic_year_id'] ?? null,
                subscriptionId: $subscriptionId,
            )
            : $this->service->classTimetable(
                gradeId: (int) $data['grade_id'],
                sectionId: $data['section_id'] ?? null,
                streamId: $data['stream_id'] ?? null,
                academicYearId: $data['academic_year_id'] ?? null,
                subscriptionId: $subscriptionId,
            );

        return response()->json([
            'success' => true,
            'data' => $result,
        ]);
    }

    public function teacher(Request $request, int $teacher): JsonResponse
    {
        $subscriptionId = $this->subscriptionId();

        $request->validate([
            'academic_year_id' => [
                'nullable',
                'integer',
                Rule::exists('academic_years', 'id')
                    ->where('subscription_id', $subscriptionId),
            ],
        ]);

        $this->validateRouteTeacher($teacher, $subscriptionId);

        return response()->json([
            'success' => true,
            'data' => $this->service->teacherTimetable(
                teacherId: $teacher,
                academicYearId: $request->integer('academic_year_id') ?: null,
                subscriptionId: $subscriptionId,
            ),
        ]);
    }

    public function classTimetable(Request $request): JsonResponse
    {
        $subscriptionId = $this->subscriptionId();

        $data = $request->validate([
            'academic_year_id' => [
                'nullable',
                'integer',
                Rule::exists('academic_years', 'id')
                    ->where('subscription_id', $subscriptionId),
            ],
            'grade_id' => [
                'required',
                'integer',
                Rule::exists('grades', 'id')
                    ->where('subscription_id', $subscriptionId),
            ],
            'section_id' => [
                'nullable',
                'integer',
                Rule::exists('sections', 'id')
                    ->where('subscription_id', $subscriptionId),
            ],
            'stream_id' => [
                'nullable',
                'integer',
                Rule::exists('streams', 'id')
                    ->where('subscription_id', $subscriptionId),
            ],
        ]);

        return response()->json([
            'success' => true,
            'data' => $this->service->classTimetable(
                gradeId: (int) $data['grade_id'],
                sectionId: $data['section_id'] ?? null,
                streamId: $data['stream_id'] ?? null,
                academicYearId: $data['academic_year_id'] ?? null,
                subscriptionId: $subscriptionId,
            ),
        ]);
    }

    public function today(Request $request): JsonResponse
    {
        $subscriptionId = $this->subscriptionId();

        $data = $request->validate([
            'academic_year_id' => [
                'nullable',
                'integer',
                Rule::exists('academic_years', 'id')
                    ->where('subscription_id', $subscriptionId),
            ],
            'teacher_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')
                    ->where('subscription_id', $subscriptionId),
            ],
            'grade_id' => [
                'nullable',
                'integer',
                Rule::exists('grades', 'id')
                    ->where('subscription_id', $subscriptionId),
            ],
            'section_id' => [
                'nullable',
                'integer',
                Rule::exists('sections', 'id')
                    ->where('subscription_id', $subscriptionId),
            ],
            'stream_id' => [
                'nullable',
                'integer',
                Rule::exists('streams', 'id')
                    ->where('subscription_id', $subscriptionId),
            ],
        ]);

        return response()->json([
            'success' => true,
            'data' => $this->service->today(
                teacherId: $data['teacher_id'] ?? null,
                gradeId: $data['grade_id'] ?? null,
                sectionId: $data['section_id'] ?? null,
                streamId: $data['stream_id'] ?? null,
                academicYearId: $data['academic_year_id'] ?? null,
                subscriptionId: $subscriptionId,
            ),
        ]);
    }

    public function freePeriods(Request $request): JsonResponse
    {
        $subscriptionId = $this->subscriptionId();

        $data = $request->validate([
            'teacher_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id')
                    ->where('subscription_id', $subscriptionId),
            ],
            'academic_year_id' => [
                'nullable',
                'integer',
                Rule::exists('academic_years', 'id')
                    ->where('subscription_id', $subscriptionId),
            ],
        ]);

        return response()->json([
            'success' => true,
            'data' => $this->service->freePeriods(
                teacherId: (int) $data['teacher_id'],
                academicYearId: $data['academic_year_id'] ?? null,
                subscriptionId: $subscriptionId,
            ),
        ]);
    }

    public function workload(Request $request): JsonResponse
    {
        $subscriptionId = $this->subscriptionId();

        $data = $request->validate([
            'teacher_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id')
                    ->where('subscription_id', $subscriptionId),
            ],
            'academic_year_id' => [
                'nullable',
                'integer',
                Rule::exists('academic_years', 'id')
                    ->where('subscription_id', $subscriptionId),
            ],
        ]);

        return response()->json([
            'success' => true,
            'data' => $this->service->workload(
                teacherId: (int) $data['teacher_id'],
                academicYearId: $data['academic_year_id'] ?? null,
                subscriptionId: $subscriptionId,
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

    private function subscriptionId(): int
    {
        $subscriptionId = $requestUserSubscriptionId = auth()->user()?->subscription_id
            ?? auth()->user()?->subscription?->id;

        abort_if(
            ! is_numeric($subscriptionId) || (int) $subscriptionId <= 0,
            403,
            'A valid subscription is required.'
        );

        return (int) $requestUserSubscriptionId;
    }

    private function validateRouteTeacher(int $teacherId, int $subscriptionId): void
    {
        validator(
            ['teacher_id' => $teacherId],
            [
                'teacher_id' => [
                    'required',
                    'integer',
                    Rule::exists('users', 'id')
                        ->where('subscription_id', $subscriptionId),
                ],
            ]
        )->validate();
    }
}