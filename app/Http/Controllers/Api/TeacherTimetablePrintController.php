<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AcademicPlanning\TeacherTimetableService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class TeacherTimetablePrintController extends Controller
{
    public function __construct(
        protected TeacherTimetableService $service
    ) {}

    public function print(Request $request): Response
    {
        $subscriptionId = $this->subscriptionId($request);

        $validated = $request->validate([
            'mode' => ['nullable', Rule::in(['teacher', 'class'])],
            'teacher_id' => [
                Rule::requiredIf(
                    fn () => $request->input('mode', 'teacher') === 'teacher'
                ),
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where(
                    fn ($query) => $query
                        ->where('subscription_id', $subscriptionId)
                        ->where('role', 'teacher')
                        ->where('status', 'active')
                        ->where('is_active', true)
                ),
            ],
            'academic_year_id' => [
                'nullable',
                'integer',
                Rule::exists('academic_years', 'id')->where(
                    fn ($query) => $query
                        ->where('subscription_id', $subscriptionId)
                        ->where('is_active', true)
                ),
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

        $mode = $validated['mode'] ?? 'teacher';
        $academicYearId = isset($validated['academic_year_id'])
            ? (int) $validated['academic_year_id']
            : null;

        $data = $mode === 'teacher'
            ? $this->service->teacherTimetable(
                teacherId: (int) $validated['teacher_id'],
                academicYearId: $academicYearId,
                subscriptionId: $subscriptionId,
            )
            : $this->service->classTimetable(
                gradeId: (int) $validated['grade_id'],
                sectionId: isset($validated['section_id'])
                    ? (int) $validated['section_id']
                    : null,
                streamId: isset($validated['stream_id'])
                    ? (int) $validated['stream_id']
                    : null,
                academicYearId: $academicYearId,
                subscriptionId: $subscriptionId,
            );

        $filename = sprintf(
            'teacher-timetable-%s-%s.pdf',
            $mode,
            now()->format('YmdHis')
        );

        return Pdf::loadView('pdf.teacher-timetable', [
            ...$data,
            'mode' => $mode,
            'filters' => [
                'teacher_id' => $validated['teacher_id'] ?? null,
                'academic_year_id' => $academicYearId,
                'grade_id' => $validated['grade_id'] ?? null,
                'section_id' => $validated['section_id'] ?? null,
                'stream_id' => $validated['stream_id'] ?? null,
            ],
        ])
            ->setPaper('a4', 'landscape')
            ->download($filename);
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
