<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Teacher;
use App\Models\Question;
use App\Models\QuestionPaper;
use App\Models\Grade;
use App\Models\Subject;

class DashboardController extends Controller
{
    public function index()
    {
        return response()->json([
            'stats' => [
                'teachers' => Teacher::count(),
                'grades' => Grade::count(),
                'subjects' => Subject::count(),
                'questions' => Question::count(),
                'question_papers' => QuestionPaper::count(),
                'published_papers' => QuestionPaper::where('is_published', true)->count(),
                'draft_papers' => QuestionPaper::where('is_published', false)->count(),
            ],

            'recent_questions' => Question::with([
                'creator',
                'grade',
                'subject',
                'lesson'
            ])
                ->latest()
                ->take(5)
                ->get(),

            'recent_papers' => QuestionPaper::with([
                'grade',
                'subject',
                'creator'
            ])
                ->latest()
                ->take(5)
                ->get(),

            'teacher_progress' => Teacher::with([
                'user',
                'assignments.grade',
                'assignments.subject'
            ])
                ->get()
                ->map(function ($teacher) {
                    return [
                        'teacher_id' => $teacher->id,
                        'name' => $teacher->user?->name,
                        'contact' => $teacher->contact,
                        'assignments_count' => $teacher->assignments->count(),
                        'questions_count' => Question::where('created_by', $teacher->user_id)->count(),
                        'papers_count' => QuestionPaper::where('created_by', $teacher->user_id)->count(),
                    ];
                }),
        ]);
    }
}
