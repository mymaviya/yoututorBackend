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
            'assignedBy'
        ])
        ->latest()
        ->get()
        ->map(function ($task) {
            $created = Question::where('created_by', $task->teacher->user_id)
                ->where('grade_id', $task->grade_id)
                ->where('subject_id', $task->subject_id)
                ->where('type', $task->question_type)
                ->where('difficulty', $task->difficulty)
                ->count();

            return [
                'id' => $task->id,
                'teacher' => $task->teacher,
                'grade' => $task->grade,
                'subject' => $task->subject,
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

    public function store(Request $request)
    {
        $data = $request->validate([
            'teacher_id' => 'required|exists:teachers,id',
            'grade_id' => 'required|exists:grades,id',
            'subject_id' => 'required|exists:subjects,id',
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
