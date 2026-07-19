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
        $subscriptionId = $this->subscriptionId($request);
        $data = $request->validate($this->indexRules($request, $subscriptionId));
        $mode = $data['mode'] ?? 'teacher';

        $result = $mode === 'teacher'
            ? $this->service->teacherTimetable(
                teacherId: (int) $data['teacher_id'],
                academicYearId: isset($data['academic_year_id']) ? (int) $data['academic_year_id'] : null,
                subscriptionId: $subscriptionId,
            )
            : $this->service->classTimetable(
                gradeId: (int) $data['grade_id'],
                sectionId: isset($data['section_id']) ? (int) $data['section_id'] : null,
                streamId: isset($data['stream_id']) ? (int) $data['stream_id'] : null,
                academicYearId: isset($data['academic_year_id']) ? (int) $data['academic_year_id'] : null,
                subscriptionId: $subscriptionId,
            );

        return response()->json(['success' => true, 'data' => $result]);
    }

    public function teacher(Request $request, int $teacher): JsonResponse
    {
        $subscriptionId = $this->subscriptionId($request);
        $validated = $request->validate([
            'academic_year_id' => $this->academicYearRules($subscriptionId),
        ]);

        $this->validateRouteTeacher($teacher, $subscriptionId);

        return response()->json([
            'success' => true,
            'data' => $this->service->teacherTimetable(
                teacherId: $teacher,
                academicYearId: isset($validated['academic_year_id']) ? (int) $validated['academic_year_id'] : null,
                subscriptionId: $subscriptionId,
            ),
        ]);
    }

    public function classTimetable(Request $request): JsonResponse
    {
        $subscriptionId = $this->subscriptionId($request);
        $data = $request->validate([
            'academic_year_id' => $this->academicYearRules($subscriptionId),
            'grade_id' => $this->gradeRules($subscriptionId, true),
            'section_id' => $this->sectionRules($subscriptionId),
            'stream_id' => $this->streamRules($subscriptionId),
        ]);

        return response()->json([
            'success' => true,
            'data' => $this->service->classTimetable(
                gradeId: (int) $data['grade_id'],
                sectionId: isset($data['section_id']) ? (int) $data['section_id'] : null,
                streamId: isset($data['stream_id']) ? (int) $data['stream_id'] : null,
                academicYearId: isset($data['academic_year_id']) ? (int) $data['academic_year_id'] : null,
                subscriptionId: $subscriptionId,
            ),
        ]);
    }

    public function today(Request $request): JsonResponse
    {
        $subscriptionId = $this->subscriptionId($request);
        $data = $request->validate([
            'academic_year_id' => $this->academicYearRules($subscriptionId),
            'teacher_id' => $this->teacherRules($subscriptionId),
            'grade_id' => $this->gradeRules($subscriptionId),
            'section_id' => $this->sectionRules($subscriptionId),
            'stream_id' => $this->streamRules($subscriptionId),
        ]);

        return response()->json([
            'success' => true,
            'data' => $this->service->today(
                teacherId: isset($data['teacher_id']) ? (int) $data['teacher_id'] : null,
                gradeId: isset($data['grade_id']) ? (int) $data['grade_id'] : null,
                sectionId: isset($data['section_id']) ? (int) $data['section_id'] : null,
                streamId: isset($data['stream_id']) ? (int) $data['stream_id'] : null,
                academicYearId: isset($data['academic_year_id']) ? (int) $data['academic_year_id'] : null,
                subscriptionId: $subscriptionId,
            ),
        ]);
    }

    public function freePeriods(Request $request): JsonResponse
    {
        return $this->teacherMetric($request, 'freePeriods');
    }

    public function workload(Request $request): JsonResponse
    {
        return $this->teacherMetric($request, 'workload');
    }

    public function print(Request $request): JsonResponse
    {
        return $this->index($request);
    }

    public function export(Request $request): JsonResponse
    {
        return $this->index($request);
    }

    private function teacherMetric(Request $request, string $method): JsonResponse
    {
        $subscriptionId = $this->subscriptionId($request);
        $data = $request->validate([
            'teacher_id' => $this->teacherRules($subscriptionId, true),
            'academic_year_id' => $this->academicYearRules($subscriptionId),
        ]);

        return response()->json([
            'success' => true,
            'data' => $this->service->{$method}(
                teacherId: (int) $data['teacher_id'],
                academicYearId: isset($data['academic_year_id']) ? (int) $data['academic_year_id'] : null,
                subscriptionId: $subscriptionId,
            ),
        ]);
    }

    private function indexRules(Request $request, int $subscriptionId): array
    {
        return [
            'mode' => ['nullable', Rule::in(['teacher', 'class'])],
            'academic_year_id' => $this->academicYearRules($subscriptionId),
            'teacher_id' => [
                Rule::requiredIf(fn () => $request->input('mode', 'teacher') === 'teacher'),
                ...$this->teacherRules($subscriptionId),
            ],
            'grade_id' => [
                Rule::requiredIf(fn () => $request->input('mode', 'teacher') === 'class'),
                ...$this->gradeRules($subscriptionId),
            ],
            'section_id' => $this->sectionRules($subscriptionId),
            'stream_id' => $this->streamRules($subscriptionId),
        ];
    }

    private function teacherRules(int $subscriptionId, bool $required = false): array
    {
        return [
            $required ? 'required' : 'nullable',
            'integer',
            Rule::exists('users', 'id')->where(
                fn ($query) => $query
                    ->where('subscription_id', $subscriptionId)
                    ->where('is_active', true)
            ),
        ];
    }

    private function academicYearRules(int $subscriptionId): array
    {
        return [
            'nullable',
            'integer',
            Rule::exists('academic_years', 'id')->where(
                fn ($query) => $query
                    ->where('subscription_id', $subscriptionId)
                    ->where('is_active', true)
            ),
        ];
    }

    private function gradeRules(int $subscriptionId, bool $required = false): array
    {
        return [
            $required ? 'required' : 'nullable',
            'integer',
            Rule::exists('grades', 'id')->where('subscription_id', $subscriptionId),
        ];
    }

    private function sectionRules(int $subscriptionId): array
    {
        return [
            'nullable',
            'integer',
            Rule::exists('sections', 'id')->where('subscription_id', $subscriptionId),
        ];
    }

    private function streamRules(int $subscriptionId): array
    {
        return [
            'nullable',
            'integer',
            Rule::exists('streams', 'id')->where('subscription_id', $subscriptionId),
        ];
    }

    private function subscriptionId(Request $request): int
    {
        $subscriptionId = $request->user()?->subscription_id
            ?? $request->user()?->subscription?->id;

        abort_if(
            !is_numeric($subscriptionId) || (int) $subscriptionId <= 0,
            403,
            'A valid subscription is required.'
        );

        return (int) $subscriptionId;
    }

    private function validateRouteTeacher(int $teacherId, int $subscriptionId): void
    {
        validator(
            ['teacher_id' => $teacherId],
            ['teacher_id' => $this->teacherRules($subscriptionId, true)]
        )->validate();
    }
}
