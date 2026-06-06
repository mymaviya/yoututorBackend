<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Teacher;
use App\Models\Question;
use App\Models\QuestionPaper;
use App\Models\Grade;
use App\Models\Subject;
use App\Models\ExamPortion;
use Illuminate\Support\Facades\DB;

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
                'approved_questions' => Question::where('status', 'approved')->count(),
                'pending_questions' => Question::where('status', 'pending')->count(),
                'rejected_questions' => Question::where('status', 'rejected')->count(),
                'question_papers' => QuestionPaper::count(),
                'published_papers' => QuestionPaper::where('is_published', true)->count(),
                'draft_papers' => QuestionPaper::where('is_published', false)->count(),
            ],

            'exam_portions' => [
                'total' => ExamPortion::count(),
                'assigned' => ExamPortion::where('status', 'assigned')->count(),
                'submitted' => ExamPortion::where('status', 'submitted')->count(),
                'approved' => ExamPortion::where('status', 'approved')->count(),
                'rejected' => ExamPortion::where('status', 'rejected')->count(),

                'recent' => ExamPortion::with([
                    'teacher.user',
                    'grade',
                    'subject',
                    'examName',
                ])->latest()->take(6)->get(),
            ],

            'recent_questions' => Question::with([
                'creator',
                'grade',
                'subject',
                'lesson',
            ])->latest()->take(6)->get(),

            'bloom_levels' => Question::select('bloom_level', DB::raw('COUNT(*) as total'))
                ->whereNotNull('bloom_level')
                ->groupBy('bloom_level')
                ->orderBy('bloom_level')
                ->get(),

            'recent_papers' => QuestionPaper::with([
                'grade',
                'subject',
                'creator',
            ])->latest()->take(6)->get(),

            'analytics' => [
                'question_status' => Question::select('status', DB::raw('COUNT(*) as total'))
                    ->groupBy('status')
                    ->get(),

                'bloom_levels' => Question::select('bloom_level', DB::raw('COUNT(*) as total'))
                    ->whereNotNull('bloom_level')
                    ->groupBy('bloom_level')
                    ->get(),

                'exam_portion_status' => ExamPortion::select('status', DB::raw('COUNT(*) as total'))
                    ->groupBy('status')
                    ->get(),

                'questions_by_grade' => Question::join('grades', 'questions.grade_id', '=', 'grades.id')
                    ->select('grades.name as grade_name', DB::raw('COUNT(*) as total'))
                    ->groupBy('grades.id', 'grades.name')
                    ->get(),

                'questions_by_subject' => Question::join('subjects', 'questions.subject_id', '=', 'subjects.id')
                    ->select('subjects.name as subject_name', DB::raw('COUNT(*) as total'))
                    ->groupBy('subjects.id', 'subjects.name')
                    ->get(),

                'teacher_performance' => Teacher::with('user')
                    ->get()
                    ->map(fn($teacher) => [
                        'name' => $teacher->user?->name,
                        'questions_count' => Question::where('created_by', $teacher->user_id)->count(),
                        'approved_questions' => Question::where('created_by', $teacher->user_id)
                            ->where('status', 'approved')
                            ->count(),
                        'exam_portions_submitted' => ExamPortion::where('teacher_id', $teacher->id)
                            ->whereIn('status', ['submitted', 'approved'])
                            ->count(),
                    ])
                    ->sortByDesc('questions_count')
                    ->values()
                    ->take(10),
            ],

            'teacher_progress' => Teacher::with([
                'user',
                'assignments.grade',
                'assignments.subject',
            ])->get()->map(function ($teacher) {
                return [
                    'teacher_id' => $teacher->id,
                    'name' => $teacher->user?->name,
                    'contact' => $teacher->contact,
                    'assignments_count' => $teacher->assignments->count(),
                    'questions_count' => Question::where('created_by', $teacher->user_id)->count(),
                    'papers_count' => QuestionPaper::where('created_by', $teacher->user_id)->count(),
                ];
            })->take(8),
        ]);
    }
}
