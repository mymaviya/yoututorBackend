<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Question;
use App\Models\QuestionPaper;
use App\Models\TeacherQuestionTask;

class TeacherDashboardController extends Controller
{
    private function createdCount(TeacherQuestionTask $task, $user): int
    {
        return Question::where('created_by', $user->id)
            ->where('grade_id', $task->grade_id)
            ->where('subject_id', $task->subject_id)
            ->where('question_type_master_id', $task->question_type_master_id)
            ->when($task->stream_id, fn ($q) => $q->where('stream_id', $task->stream_id))
            ->when($task->lesson_id, fn ($q) => $q->where('lesson_id', $task->lesson_id))
            ->count();
    }

    public function index()
    {
        $user = auth()->user();

        $tasks = TeacherQuestionTask::with(['grade', 'stream', 'subject', 'lesson', 'questionType', 'assignedBy'])
            ->where('teacher_id', $user->id)
            ->latest()
            ->get()
            ->map(function ($task) use ($user) {
                $created = $this->createdCount($task, $user);

                return [
                    'id' => $task->id,
                    'grade' => $task->grade,
                    'stream' => $task->stream,
                    'subject' => $task->subject,
                    'lesson' => $task->lesson,
                    'question_type_master_id' => $task->question_type_master_id,
                    'question_type' => $task->questionType?->slug,
                    'question_type_name' => $task->questionType?->name,
                    'target_count' => $task->target_count,
                    'created_count' => $created,
                    'remaining_count' => max($task->target_count - $created, 0),
                    'progress' => $task->target_count > 0 ? min(round(($created / $task->target_count) * 100), 100) : 0,
                    'due_date' => $task->due_date,
                    'status' => $created >= $task->target_count ? 'completed' : $task->status,
                    'assigned_by' => $task->assignedBy,
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
            'assignments' => $user->teacherAssignments()->with(['grade', 'stream', 'subject'])->get(),
            'tasks' => $tasks,
            'recent_questions' => Question::with(['grade', 'stream', 'subject', 'lesson', 'type'])->where('created_by', $user->id)->latest()->take(5)->get(),
            'recent_papers' => QuestionPaper::with(['grade', 'stream', 'subject'])->where('created_by', $user->id)->latest()->take(5)->get(),
        ]);
    }
}
