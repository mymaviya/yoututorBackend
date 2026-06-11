<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Question;
use App\Models\QuestionTypeMaster;
use App\Models\TeacherQuestionTask;
use Illuminate\Http\Request;

class TeacherQuestionTaskController extends Controller
{
    private function resolveQuestionTypeId($value): ?int
    {
        if (is_numeric($value)) {
            return (int) $value;
        }

        return QuestionTypeMaster::where('slug', $value)
            ->orWhere('name', $value)
            ->value('id');
    }

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
        $tasks = TeacherQuestionTask::with(['teacher', 'grade', 'stream', 'subject', 'lesson', 'questionType', 'assignedBy'])
            ->latest()
            ->get()
            ->map(function ($task) {
                $created = $task->teacher ? $this->createdCount($task, $task->teacher) : 0;

                return [
                    'id' => $task->id,
                    'teacher' => $task->teacher,
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

        return response()->json($tasks);
    }

    public function myTasks()
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

        return response()->json($tasks);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'teacher_id' => 'required|exists:users,id',
            'grade_id' => 'required|exists:grades,id',
            'stream_id' => 'nullable|exists:streams,id',
            'subject_id' => 'required|exists:subjects,id',
            'lesson_id' => 'nullable|exists:lessons,id',
            'question_type_master_ids' => 'nullable|array',
            'question_type_master_ids.*' => 'required',
            'question_types' => 'nullable|array',
            'question_types.*' => 'required',
            'target_count' => 'required|integer|min:1',
            'due_date' => 'required|date',
            'remarks' => 'nullable|string',
        ]);

        $rawTypes = $data['question_type_master_ids'] ?? $data['question_types'] ?? [];

        if (!count($rawTypes)) {
            return response()->json([
                'message' => 'Please select at least one question type.',
                'errors' => ['question_types' => ['Please select at least one question type.']],
            ], 422);
        }

        $createdTasks = [];

        foreach ($rawTypes as $rawType) {
            $questionTypeId = $this->resolveQuestionTypeId($rawType);

            if (!$questionTypeId) {
                continue;
            }

            $task = TeacherQuestionTask::create([
                'teacher_id' => $data['teacher_id'],
                'assigned_by' => auth()->id(),
                'grade_id' => $data['grade_id'],
                'stream_id' => $data['stream_id'] ?? null,
                'subject_id' => $data['subject_id'],
                'lesson_id' => $data['lesson_id'] ?? null,
                'question_type_master_id' => $questionTypeId,
                'target_count' => $data['target_count'],
                'due_date' => $data['due_date'],
                'status' => 'assigned',
                'remarks' => $data['remarks'] ?? null,
            ]);

            $task->load(['teacher', 'grade', 'stream', 'subject', 'lesson', 'questionType']);

            notifyUser(
                $task->teacher_id,
                'New Question Task Assigned',
                'You have been assigned a new question creation task for ' . $task->grade->name . ' - ' . $task->subject->name . ' (' . $task->questionType?->name . ')',
                'task',
                '/my-question-tasks'
            );

            $createdTasks[] = $task;
        }

        return response()->json([
            'message' => 'Tasks assigned successfully',
            'data' => $createdTasks,
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $task = TeacherQuestionTask::findOrFail($id);

        $data = $request->validate([
            'teacher_id' => 'required|exists:users,id',
            'grade_id' => 'required|exists:grades,id',
            'stream_id' => 'nullable|exists:streams,id',
            'subject_id' => 'required|exists:subjects,id',
            'lesson_id' => 'nullable|exists:lessons,id',
            'question_type_master_id' => 'required|exists:question_type_masters,id',
            'target_count' => 'required|integer|min:1',
            'due_date' => 'required|date',
            'status' => 'nullable|string',
            'remarks' => 'nullable|string',
        ]);

        $task->update($data);

        return response()->json([
            'message' => 'Task updated successfully',
            'data' => $task->fresh()->load(['teacher', 'grade', 'stream', 'subject', 'lesson', 'questionType']),
        ]);
    }

    public function destroy($id)
    {
        TeacherQuestionTask::findOrFail($id)->delete();

        return response()->json(['message' => 'Task deleted successfully']);
    }
}
