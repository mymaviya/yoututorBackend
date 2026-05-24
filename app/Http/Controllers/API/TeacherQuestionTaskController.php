<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\TeacherQuestionTask;
use App\Models\QuestionMatchPair;
use App\Models\Question;
use Illuminate\Http\Request;

class TeacherQuestionTaskController extends Controller
{
    public function index()
    {
        $tasks = TeacherQuestionTask::with([
            'teacher.user',
            'grade',
            'subject',
            'lesson',
            'questionTypeData',
            'assignedBy'
        ])
            ->latest()
            ->get()
            ->map(function ($task) {

                $user = $task->teacher?->user;

                $created = $this->getCreatedCount($task, $user);

                $progress = $task->target_count > 0
                    ? min(round(($created / $task->target_count) * 100), 100)
                    : 0;

                return [
                    'id' => $task->id,
                    'teacher' => $task->teacher,
                    'grade' => $task->grade,
                    'subject' => $task->subject,
                    'lesson' => $task->lesson,
                    'question_type' => $task->question_type,
                    'question_type_data' => $task->questionTypeData,
                    'question_type_name' => $task->questionTypeData?->name ?? $task->question_type,
                    'difficulty' => $task->difficulty,
                    'target_count' => $task->target_count,
                    'created_count' => $created,
                    'remaining_count' => max($task->target_count - $created, 0),
                    'progress' => $progress,
                    'due_date' => $task->due_date,
                    'status' => $created >= $task->target_count
                        ? 'completed'
                        : ($created > 0 ? 'in_progress' : 'pending'),
                ];
            });

        return response()->json($tasks);
    }

    private function getCreatedCount($task, $user)
    {
        if (!$user) {
            return 0;
        }

        $contentBasedTypes = [
            'word_meaning',
            'make_sentence',
            'difficult_words',
        ];

        if ($task->question_type === 'match_column') {
            return QuestionMatchPair::whereHas('question', function ($q) use ($task, $user) {
                $q->where('created_by', $user->id)
                    ->where('grade_id', $task->grade_id)
                    ->where('subject_id', $task->subject_id)
                    ->where('type', 'match_column')
                    ->where('difficulty', $task->difficulty)
                    ->when($task->lesson_id, function ($query) use ($task) {
                        $query->where('lesson_id', $task->lesson_id);
                    });
            })->count();
        }

        if (in_array($task->question_type, $contentBasedTypes)) {
            return Question::withCount('languageItems')
                ->where('created_by', $user->id)
                ->where('grade_id', $task->grade_id)
                ->where('subject_id', $task->subject_id)
                ->where('type', $task->question_type)
                ->where('difficulty', $task->difficulty)
                ->when($task->lesson_id, function ($query) use ($task) {
                    $query->where('lesson_id', $task->lesson_id);
                })
                ->get()
                ->sum('language_items_count');
        }

        return Question::where('created_by', $user->id)
            ->where('grade_id', $task->grade_id)
            ->where('subject_id', $task->subject_id)
            ->where('type', $task->question_type)
            ->where('difficulty', $task->difficulty)
            ->when($task->lesson_id, function ($query) use ($task) {
                $query->where('lesson_id', $task->lesson_id);
            })
            ->count();
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

                if ($task->question_type === 'match_column') {
                    $created = $this->getCreatedCount($task, $user);
                } else {
                    $created = $this->getCreatedCount($task, $user);
                }

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
            'question_types' => 'required|array|min:1',
            'question_types.*' => 'required|string',
            'difficulty' => 'required|string',
            'target_count' => 'required|integer|min:1',
            'due_date' => 'required|date'
        ]);


        foreach ($data['question_types'] as $type) {

            $task = TeacherQuestionTask::create([
                'teacher_id' => $data['teacher_id'],
                'grade_id' => $data['grade_id'],
                'subject_id' => $data['subject_id'],
                'lesson_id' => $data['lesson_id'] ?? null,
                'question_type' => $type,
                'difficulty' => $data['difficulty'],
                'target_count' => $data['target_count'],
                'due_date' => $data['due_date'] ?? null,
                'assigned_by' => auth()->id(),
                'status' => 'pending',
            ]);

            $task->load([
                'teacher.user',
                'grade',
                'subject',
                'lesson'
            ]);

            notifyUser(
                $task->teacher->user_id,
                'New Question Task Assigned',
                'You have been assigned a new question creation task for '
                    . $task->grade->name
                    . ' - '
                    . $task->subject->name
                    . ' ('
                    . strtoupper($task->question_type)
                    . ')',
                'task',
                '/my-question-tasks'
            );
        }

        return response()->json([
            'message' => 'Tasks assigned successfully',
            'data' => $task
        ], 201);
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
            'due_date' => 'required|date',
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
