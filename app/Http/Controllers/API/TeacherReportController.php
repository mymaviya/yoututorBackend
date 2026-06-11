<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Question;
use App\Models\QuestionPaper;
use App\Models\User;

class TeacherReportController extends Controller
{
    private function teachers()
    {
        return User::query()
            ->where(function ($q) {
                $q->where('role', 'teacher')
                    ->orWhereHas('roleData', fn ($role) => $role->where('slug', 'teacher'));
            });
    }

    public function questionPaperProgress()
    {
        $report = $this->teachers()
            ->with(['teacherAssignments.grade', 'teacherAssignments.stream', 'teacherAssignments.subject'])
            ->get()
            ->map(function ($teacher) {
                return [
                    'teacher_id' => $teacher->id,
                    'name' => $teacher->name,
                    'email' => $teacher->email,
                    'contact' => $teacher->contact,
                    'is_active' => $teacher->is_active,
                    'assignments' => $teacher->teacherAssignments->map(fn ($a) => [
                        'grade' => $a->grade?->name,
                        'stream' => $a->stream?->name,
                        'subject' => $a->subject?->name,
                    ]),
                    'total_questions' => Question::where('created_by', $teacher->id)->count(),
                    'total_papers' => QuestionPaper::where('created_by', $teacher->id)->count(),
                    'published_papers' => QuestionPaper::where('created_by', $teacher->id)->where('status', 'published')->count(),
                    'draft_papers' => QuestionPaper::where('created_by', $teacher->id)->where('status', 'draft')->count(),
                ];
            });

        return response()->json($report);
    }
}
