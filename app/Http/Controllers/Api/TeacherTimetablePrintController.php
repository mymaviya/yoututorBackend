<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AcademicPlanning\TeacherTimetableService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TeacherTimetablePrintController extends Controller
{
    public function __construct(
        protected TeacherTimetableService $service
    ) {}

    public function print(Request $request)
    {
        $validated = $request->validate([
            'mode' => ['nullable', Rule::in(['teacher', 'class'])],
            'teacher_id' => [
                Rule::requiredIf(fn () => $request->input('mode', 'teacher') === 'teacher'),
                'nullable',
                'integer',
                'exists:users,id',
            ],
            'academic_year_id' => [
                'nullable',
                'integer',
                'exists:academic_years,id',
            ],
            'grade_id' => [
                Rule::requiredIf(fn () => $request->input('mode', 'teacher') === 'class'),
                'nullable',
                'integer',
                'exists:grades,id',
            ],
            'section_id' => [
                'nullable',
                'integer',
                'exists:sections,id',
            ],
            'stream_id' => [
                'nullable',
                'integer',
                'exists:streams,id',
            ],
        ]);

        $mode = $validated['mode'] ?? 'teacher';
        $subscriptionId = $request->user()?->subscription_id;

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

        return Pdf::loadView('pdf.teacher-timetable', $data)
            ->setPaper('a4', 'landscape')
            ->download($filename);
    }
}