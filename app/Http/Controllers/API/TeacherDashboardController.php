<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Question;
use App\Models\QuestionPaper;
use App\Models\TeacherQuestionTask;

class TeacherDashboardController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        $teacher = $user->teacher;

        if (!$teacher) {
            return response()->json([
                'message' => 'Teacher profile not found.'
            ], 404);
        }

        $tasks = TeacherQuestionTask::with(['grade', 'subject'])
            ->where('teacher_id', $teacher->id)
            ->latest()
            ->get()
            ->map(function ($task) use ($user) {
                $created = Question::where('created_by', $user->id)
                    ->where('grade_id', $task->grade_id)
                    ->where('subject_id', $task->subject_id)
                    ->where('type', $task->question_type)
                    ->where('difficulty', $task->difficulty)
                    ->count();

                return [
                    'id' => $task->id,
                    'grade' => $task->grade,
                    'subject' => $task->subject,
                    'question_type' => $task->question_type,
                    'difficulty' => $task->difficulty,
                    'target_count' => $task->target_count,
                    'created_count' => $created,
                    'remaining_count' => max($task->target_count - $created, 0),
                    'progress' => $task->target_count > 0
                        ? min(round(($created / $task->target_count) * 100), 100)
                        : 0,
                    'due_date' => $task->due_date,
                    'status' => $created >= $task->target_count
                        ? 'completed'
                        : $task->status,
                ];
            });

        return response()->json([
            'stats' => [
                'assigned_tasks' => $tasks->count(),
                'completed_tasks' => $tasks->where('status', 'completed')->count(),
                'pending_tasks' => $tasks->where('status', '!=', 'completed')->count(),
                'questions_created' => Question::where('created_by', $user->id)->count(),
                'papers_created' => QuestionPaper::where('created_by', $user->id)->count(),
            ],

            'assignments' => $teacher->assignments()
                ->with(['grade', 'subject'])
                ->get(),

            'tasks' => $tasks,

            'recent_questions' => Question::with(['grade', 'subject', 'lesson'])
                ->where('created_by', $user->id)
                ->latest()
                ->take(5)
                ->get(),

            'recent_papers' => QuestionPaper::with(['grade', 'subject'])
                ->where('created_by', $user->id)
                ->latest()
                ->take(5)
                ->get(),
        ]);
    }
}
