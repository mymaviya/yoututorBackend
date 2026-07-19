<?php

namespace App\Http\Controllers\Api;

use App\Exports\TeacherTimetableExport;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;

class TeacherTimetableExportController extends Controller
{
    public function export(Request $request)
    {
        $subscriptionId = $request->user()?->subscription_id;

        abort_if(! $subscriptionId, 403, 'A valid subscription is required.');

        $validated = $request->validate([
            'mode' => ['nullable', Rule::in(['teacher', 'class'])],
            'teacher_id' => [
                Rule::requiredIf(fn () => $request->input('mode', 'teacher') === 'teacher'),
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where(
                    fn ($query) => $query->where('subscription_id', $subscriptionId)
                ),
            ],
            'academic_year_id' => [
                'nullable',
                'integer',
                Rule::exists('academic_years', 'id')->where(
                    fn ($query) => $query->where('subscription_id', $subscriptionId)
                ),
            ],
            'grade_id' => [
                Rule::requiredIf(fn () => $request->input('mode', 'teacher') === 'class'),
                'nullable',
                'integer',
                Rule::exists('grades', 'id')->where(
                    fn ($query) => $query->where('subscription_id', $subscriptionId)
                ),
            ],
            'section_id' => [
                'nullable',
                'integer',
                Rule::exists('sections', 'id')->where(
                    fn ($query) => $query->where('subscription_id', $subscriptionId)
                ),
            ],
            'stream_id' => [
                'nullable',
                'integer',
                Rule::exists('streams', 'id')->where(
                    fn ($query) => $query->where('subscription_id', $subscriptionId)
                ),
            ],
        ]);

        $mode = $validated['mode'] ?? 'teacher';

        return Excel::download(
            new TeacherTimetableExport(
                teacherId: $mode === 'teacher' ? (int) $validated['teacher_id'] : null,
                academicYearId: $validated['academic_year_id'] ?? null,
                gradeId: $mode === 'class' ? (int) $validated['grade_id'] : null,
                sectionId: $mode === 'class' ? ($validated['section_id'] ?? null) : null,
                streamId: $mode === 'class' ? ($validated['stream_id'] ?? null) : null,
                subscriptionId: (int) $subscriptionId,
            ),
            sprintf(
                'teacher-timetable-%s-%s.xlsx',
                $mode,
                now()->format('YmdHis')
            )
        );
    }
}