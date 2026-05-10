<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Teacher;
use App\Models\Question;
use App\Models\QuestionPaper;


class TeacherReportController extends Controller
{
    public function questionPaperProgress()
    {
        $teachers = Teacher::with([
            'user',
            'assignments.grade',
            'assignments.subject'
        ])->get();

        $report = $teachers->map(function ($teacher) {

            $userId = $teacher->user_id;

            return [
                'teacher_id' => $teacher->id,
                'name' => $teacher->user?->name,
                'email' => $teacher->user?->email,
                'contact' => $teacher->contact,
                'is_active' => $teacher->is_active,

                'assignments' => $teacher->assignments->map(function ($a) {
                    return [
                        'grade' => $a->grade?->name,
                        'subject' => $a->subject?->name,
                    ];
                }),

                'total_questions' => Question::where('created_by', $userId)->count(),

                'total_papers' => QuestionPaper::where('created_by', $userId)->count(),

                'published_papers' => QuestionPaper::where('created_by', $userId)
                    ->where('is_published', true)
                    ->count(),

                'draft_papers' => QuestionPaper::where('created_by', $userId)
                    ->where('is_published', false)
                    ->count(),
            ];
        });

        return response()->json($report);
    }
}
