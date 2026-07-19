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
        $subscriptionId = $this->subscriptionId();

        $validated = $request->validate([
            'mode' => ['nullable', Rule::in(['teacher', 'class'])],
            'teacher_id' => [
                Rule::requiredIf(
                    fn () => $request->input('mode', 'teacher') === 'teacher'
                ),
                'nullable',
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

        $data = $mode === 'teacher'
            ? $this->service->teacherTimetable(
                teacherId: (int) $validated['teacher_id'],
                academicYearId: $validated['academic_year_id'] ?? null,
                subscriptionId: $subscriptionId,
            )
            : $this->service->classTimetable(
                gradeId: (int) $validated['grade_id'],
                sectionId: $validated['section_id'] ?? null,
                streamId: $validated['stream_id'] ?? null,
                academicYearId: $validated['academic_year_id'] ?? null,
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
                'academic_year_id' => $validated['academic_year_id'] ?? null,
                'grade_id' => $validated['grade_id'] ?? null,
                'section_id' => $validated['section_id'] ?? null,
                'stream_id' => $validated['stream_id'] ?? null,
            ],
        ])
            ->setPaper('a4', 'landscape')
            ->download($filename);
    }

    private function subscriptionId(): int
    {
        $subscriptionId = auth()->user()?->subscription_id
            ?? auth()->user()?->subscription?->id;

        abort_if(
            ! is_numeric($subscriptionId) || (int) $subscriptionId <= 0,
            403,
            'A valid subscription is required.'
        );

        return (int) $subscriptionId;
    }
}