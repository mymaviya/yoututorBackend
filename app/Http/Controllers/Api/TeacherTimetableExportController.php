<?php

namespace App\Http\Controllers\Api;

use App\Exports\TeacherTimetableExport;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class TeacherTimetableExportController extends Controller
{
    public function export(Request $request)
    {
        $request->validate([
            'teacher_id' => ['nullable', 'exists:users,id'],
            'academic_year_id' => ['nullable', 'exists:academic_years,id'],
            'grade_id' => ['nullable', 'exists:grades,id'],
            'section_id' => ['nullable', 'exists:sections,id'],
            'stream_id' => ['nullable', 'exists:streams,id'],
        ]);

        $subscriptionId = auth()->user()?->subscription_id;

        return Excel::download(
            new TeacherTimetableExport(
                teacherId: $request->teacher_id,
                academicYearId: $request->academic_year_id,
                gradeId: $request->grade_id,
                sectionId: $request->section_id,
                streamId: $request->stream_id,
                subscriptionId: $subscriptionId,
            ),
            'teacher-timetable-' . now()->format('Y-m-d-His') . '.xlsx'
        );
    }
}