<?php

namespace App\Http\Controllers\Api;

use App\Exports\TeacherTimetableExport;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class TeacherTimetableExportController extends Controller
{
    public function export(Request $request): BinaryFileResponse
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

        return Excel::download(
            new TeacherTimetableExport(
                subscriptionId: $subscriptionId,
                teacherId: $mode === 'teacher'
                    ? (int) $validated['teacher_id']
                    : null,
                academicYearId: isset($validated['academic_year_id'])
                    ? (int) $validated['academic_year_id']
                    : null,
                gradeId: $mode === 'class'
                    ? (int) $validated['grade_id']
                    : null,
                sectionId: $mode === 'class' && isset($validated['section_id'])
                    ? (int) $validated['section_id']
                    : null,
                streamId: $mode === 'class' && isset($validated['stream_id'])
                    ? (int) $validated['stream_id']
                    : null,
            ),
            sprintf(
                'teacher-timetable-%s-%s.xlsx',
                $mode,
                now()->format('YmdHis')
            )
        );
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
