<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\TeacherQuestionTask;
use App\Models\Question;
use Illuminate\Http\Request;

class TeacherQuestionTaskController extends Controller
{
    public function index()
    {
        return TeacherQuestionTask::with([
            'teacher.user',
            'grade',
            'subject',
            'lesson',
            'assignedBy'
        ])
        ->latest()
        ->get()
        ->map(function ($task) {
            $created = Question::where('created_by', $task->teacher->user_id)
                ->where('grade_id', $task->grade_id)
                ->where('subject_id', $task->subject_id)
                ->where('lesson_id', $task->lesson_id)
                ->where('type', $task->question_type)
                ->where('difficulty', $task->difficulty)
                ->count();

            return [
                'id' => $task->id,
                'teacher' => $task->teacher,
                'grade' => $task->grade,
                'subject' => $task->subject,
                'lesson' => $task->lesson,
                'question_type' => $task->question_type,
                'difficulty' => $task->difficulty,
                'target_count' => $task->target_count,
                'created_count' => $created,
                'remaining_count' => max($task->target_count - $created, 0),
                'progress' => $task->target_count > 0
                    ? round(($created / $task->target_count) * 100)
                    : 0,
                'due_date' => $task->due_date,
                'status' => $created >= $task->target_count
                    ? 'completed'
                    : $task->status,
            ];
        });
    }

    public function myTasks()
    {
        $user = auth()->user();

        $teacher = $user->teacher;

        if (!$teacher) {
            return response()->json([
                'message' => 'Teacher profile not found.'
            ], 404);
        }

        $tasks = TeacherQuestionTask::with([
                'grade',
                'subject',
                'assignedBy'
            ])
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

                $progress = $task->target_count > 0
                    ? min(round(($created / $task->target_count) * 100), 100)
                    : 0;

                $status = $created >= $task->target_count
                    ? 'completed'
                    : ($created > 0 ? 'in_progress' : 'pending');

                return [
                    'id' => $task->id,
                    'grade' => $task->grade,
                    'subject' => $task->subject,
                    'lesson' => $task->lesson,
                    'question_type' => $task->question_type,
                    'difficulty' => $task->difficulty,
                    'target_count' => $task->target_count,
                    'created_count' => $created,
                    'remaining_count' => max($task->target_count - $created, 0),
                    'progress' => $progress,
                    'due_date' => $task->due_date,
                    'status' => $status,
                    'assigned_by' => $task->assignedBy,
                ];
            });

        return response()->json($tasks);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'teacher_id' => 'required|exists:teachers,id',
            'grade_id' => 'required|exists:grades,id',
            'subject_id' => 'required|exists:subjects,id',
            'lesson_id' => 'nullable|exists:lessons,id',
            'question_type' => 'required|string',
            'difficulty' => 'required|string',
            'target_count' => 'required|integer|min:1',
            'due_date' => 'nullable|date',
        ]);

        $data['assigned_by'] = auth()->id();

        $task = TeacherQuestionTask::create($data);

        return response()->json([
            'message' => 'Task assigned successfully',
            'data' => $task
        ]);
    }

    public function update(Request $request, $id)
    {
        $task = TeacherQuestionTask::findOrFail($id);

        $data = $request->validate([
            'teacher_id' => 'required|exists:teachers,id',
            'grade_id' => 'required|exists:grades,id',
            'subject_id' => 'required|exists:subjects,id',
            'lesson_id' => 'nullable|exists:lessons,id',
            'question_type' => 'required|string',
            'difficulty' => 'required|string',
            'target_count' => 'required|integer|min:1',
            'due_date' => 'nullable|date',
            'status' => 'nullable|string',
        ]);

        $task->update($data);

        return response()->json([
            'message' => 'Task updated successfully',
            'data' => $task
        ]);
    }

    public function destroy($id)
    {
        TeacherQuestionTask::findOrFail($id)->delete();

        return response()->json([
            'message' => 'Task deleted successfully'
        ]);
    }
}
