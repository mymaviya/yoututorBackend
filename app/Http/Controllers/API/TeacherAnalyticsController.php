<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Question;
use App\Models\TeacherQuestionTask;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class TeacherAnalyticsController extends Controller
{
    private function teachers()
    {
        return User::query()
            ->where(function ($q) {
                $q->where('role', 'teacher')
                    ->orWhereHas('roleData', fn ($role) => $role->where('slug', 'teacher'));
            });
    }

    public function index()
    {
        return response()->json([
            'summary' => [
                'total_teachers' => $this->teachers()->count(),
                'total_tasks' => TeacherQuestionTask::count(),
                'completed_tasks' => TeacherQuestionTask::whereIn('status', ['completed', 'approved'])->count(),
                'pending_tasks' => TeacherQuestionTask::whereIn('status', ['assigned', 'pending', 'in_progress'])->count(),
                'total_questions' => Question::count(),
                'approved_questions' => Question::where('status', 'approved')->count(),
                'pending_questions' => Question::where('status', 'pending')->count(),
                'rejected_questions' => Question::where('status', 'rejected')->count(),
            ],
            'status_distribution' => Question::select('status', DB::raw('COUNT(*) as total'))->groupBy('status')->get(),
            'difficulty_distribution' => Question::select('difficulty', DB::raw('COUNT(*) as total'))->groupBy('difficulty')->get(),
            'question_type_distribution' => Question::join('question_type_masters', 'questions.question_type_master_id', '=', 'question_type_masters.id')
                ->select('question_type_masters.name', DB::raw('COUNT(*) as total'))
                ->groupBy('question_type_masters.id', 'question_type_masters.name')
                ->orderByDesc('total')
                ->get(),
            'monthly_submissions' => Question::select(DB::raw("DATE_FORMAT(created_at, '%b %Y') as month"), DB::raw('COUNT(*) as total'))
                ->groupBy(DB::raw("DATE_FORMAT(created_at, '%b %Y')"))
                ->orderByRaw('MIN(created_at)')
                ->get(),
            'teacher_progress' => $this->teachers()->get()->map(function ($teacher) {
                $tasks = TeacherQuestionTask::where('teacher_id', $teacher->id)->get();
                $completed = $tasks->whereIn('status', ['completed', 'approved'])->count();

                return [
                    'teacher_id' => $teacher->id,
                    'name' => $teacher->name,
                    'total_tasks' => $tasks->count(),
                    'completed_tasks' => $completed,
                    'pending_tasks' => $tasks->count() - $completed,
                    'progress' => $tasks->count() ? round(($completed / $tasks->count()) * 100) : 0,
                ];
            })->values(),
        ]);
    }
}
