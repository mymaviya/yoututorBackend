<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Question;
use App\Models\Teacher;
use App\Models\TeacherQuestionTask;
use Illuminate\Support\Facades\DB;

class TeacherAnalyticsController extends Controller
{
    public function index()
    {
        $contentBasedTypes = [
            'word_meaning',
            'make_sentence',
            'difficult_words',
        ];

        $questions = Question::withCount('languageItems')->get();

        $totalQuestions = $questions->sum(function ($question) use ($contentBasedTypes) {
            return in_array($question->type, $contentBasedTypes)
                ? max(1, $question->language_items_count)
                : 1;
        });

        return response()->json([
            'summary' => [
                'total_teachers' => Teacher::count(),
                'total_tasks' => TeacherQuestionTask::count(),
                'completed_tasks' => TeacherQuestionTask::where('status', 'completed')->count(),
                'pending_tasks' => TeacherQuestionTask::where('status', 'pending')->count(),
                'total_questions' => $totalQuestions,
                'approved_questions' => Question::where('status', 'approved')->count(),
                'pending_questions' => Question::where('status', 'pending')->count(),
                'rejected_questions' => Question::where('status', 'rejected')->count(),
            ],

            'status_distribution' => Question::select('status', DB::raw('COUNT(*) as total'))
                ->groupBy('status')
                ->get(),

            'difficulty_distribution' => Question::select('difficulty', DB::raw('COUNT(*) as total'))
                ->groupBy('difficulty')
                ->get(),

            'question_type_distribution' => Question::select('type', DB::raw('COUNT(*) as total'))
                ->groupBy('type')
                ->orderByDesc('total')
                ->get(),

            'monthly_submissions' => Question::select(
                    DB::raw("DATE_FORMAT(created_at, '%b %Y') as month"),
                    DB::raw('COUNT(*) as total')
                )
                ->groupBy(DB::raw("DATE_FORMAT(created_at, '%b %Y')"))
                ->orderByRaw('MIN(created_at)')
                ->get(),

            'teacher_progress' => Teacher::with('user')
                ->get()
                ->map(function ($teacher) {
                    $tasks = TeacherQuestionTask::where('teacher_id', $teacher->id)->get();

                    return [
                        'teacher_id' => $teacher->id,
                        'name' => $teacher->user?->name,
                        'total_tasks' => $tasks->count(),
                        'completed_tasks' => $tasks->where('status', 'completed')->count(),
                        'pending_tasks' => $tasks->where('status', 'pending')->count(),
                        'progress' => $tasks->count()
                            ? round(($tasks->where('status', 'completed')->count() / $tasks->count()) * 100)
                            : 0,
                    ];
                })
                ->values(),
        ]);
    }
}
