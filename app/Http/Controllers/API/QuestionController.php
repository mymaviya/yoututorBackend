<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\TeacherQuestionTask;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

class QuestionController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | GET ALL QUESTIONS (FILTER + PAGINATION)
    |--------------------------------------------------------------------------
    */

    public function index(Request $request)
    {
        $query = Question::with([
            'creator',
            'grade',
            'subject',
            'lesson',
            'options'
        ]);

        if (auth()->user()->role !== 'admin') {
            $query->where('created_by', auth()->id());
        }

        if ($request->filled('for_paper') && $request->for_paper == 1) {
            $query->where('status', 'approved');
        }

        if ($request->status) {
            $query->where('status', $request->status);
        }

        if ($request->grade_id) {
            $query->where('grade_id', $request->grade_id);
        }

        if ($request->subject_id) {
            $query->where('subject_id', $request->subject_id);
        }

        if ($request->lesson_id) {
            $query->where('lesson_id', $request->lesson_id);
        }

        if ($request->type) {
            $query->where('type', $request->type);
        }

        if ($request->difficulty) {
            $query->where('difficulty', $request->difficulty);
        }

        if ($request->search) {
            $query->where('question', 'like', '%' . $request->search . '%');
        }

        return $query->latest()->paginate(10);
    }

    /* STORE QUESTION */

    public function store(Request $request)
    {
        DB::beginTransaction();



        $request->validate([
            'grade_id' => 'required|exists:grades,id',
            'subject_id' => 'required|exists:subjects,id',
            'lesson_id' => 'required|exists:lessons,id',
            'task_id' => 'nullable|exists:teacher_question_tasks,id',
            'question' => 'required',
            'type' => 'required',
            'difficulty' => 'required',
            'marks' => 'required|numeric',
            'options' => 'nullable|array'
        ]);


        // Restricted Teachers only to update thier assigned classes and Subjects

        if (auth()->user()->role === 'teacher') {
            $allowed = auth()->user()
                ->teacher
                ->assignments()
                ->where('grade_id', $request->grade_id)
                ->where('subject_id', $request->subject_id)
                ->exists();

            if (!$allowed) {
                return response()->json([
                    'message' => 'You are not assigned to this grade and subject.'
                ], 403);
            }
        }

        // Restricted Teachers only to add Number of Assinged questions in thier category

        if (auth()->user()->role === 'teacher') {

            $teacher = auth()->user()->teacher;

            $taskQuery = TeacherQuestionTask::where('teacher_id', $teacher->id)
                ->where('grade_id', $request->grade_id)
                ->where('subject_id', $request->subject_id)
                ->where('question_type', $request->type)
                ->where('difficulty', $request->difficulty);

            if ($request->filled('lesson_id')) {
                $taskQuery->where(function ($q) use ($request) {
                    $q->whereNull('lesson_id')
                        ->orWhere('lesson_id', $request->lesson_id);
                });
            }

            if ($request->filled('task_id')) {
                $taskQuery->where('id', $request->task_id);
            }

            $task = $taskQuery->first();

            if (!$task) {
                return response()->json([
                    'message' => 'No assigned task found for this grade, subject, type and difficulty.'
                ], 403);
            }

            $createdCount = Question::where('created_by', auth()->id())
                ->where('grade_id', $task->grade_id)
                ->where('subject_id', $task->subject_id)
                ->when($task->lesson_id, function ($query) use ($task) {
                    $query->where('lesson_id', $task->lesson_id);
                })
                ->where('type', $task->question_type)
                ->where('difficulty', $task->difficulty)
                ->count();

            if ($createdCount >= $task->target_count) {
                return response()->json([
                    'message' => 'This task is already completed. You cannot add more questions for this task.'
                ], 403);
            }
        }

        /* QUESTION IMAGE */

        $questionImagePath = null;

        if ($request->hasFile('question_image')) {

            $questionImagePath = $request->file('question_image')
                ->store('questions', 'public');
        }

        /* CREATE QUESTION */

        $question = Question::create([
            'grade_id' => $request->grade_id,
            'subject_id' => $request->subject_id,
            'lesson_id' => $request->lesson_id,
            'question' => $request->question,
            'type' => $request->type,
            'difficulty' => $request->difficulty,
            'marks' => $request->marks,
            'answer' => $request->answer,
            'explanation' => $request->explanation,
            'question_image' => $questionImagePath,
            'created_by' => auth()->id(),
            'status' => auth()->user()->role === 'admin' ? 'approved' : 'pending',
        ]);

        /* OPTIONS (MCQ) */

        if ($request->options) {

            foreach ($request->options as $index => $opt) {

                $optionImage = null;

                if ($request->hasFile("options.$index.option_image")) {

                    $optionImage = $request->file("options.$index.option_image")
                        ->store('question-options', 'public');
                }

                QuestionOption::create([
                    'question_id' => $question->id,

                    'option_text' => $opt['option_text'] ?? null,

                    'option_image' => $optionImage,

                    'is_correct' => $opt['is_correct'] ?? false
                ]);
            }
        }

        DB::commit();

        return response()->json([
            'message' => 'Question created successfully',
            'data' => $question->load('options')
        ]);
    }

    /* SHOW SINGLE QUESTION */

    public function show($id)
    {
        return Question::with([
            'grade',
            'subject',
            'lesson',
            'options'
        ])->findOrFail($id);
    }

    /* UPDATE QUESTION */

    public function update(Request $request, $id)
    {
        DB::beginTransaction();

        try {

            $question = Question::findOrFail($id);

            if (auth()->user()->role === 'teacher') {
                $allowed = auth()->user()
                    ->teacher
                    ->assignments()
                    ->where('grade_id', $request->grade_id)
                    ->where('subject_id', $request->subject_id)
                    ->exists();

                if (!$allowed) {
                    return response()->json([
                        'message' => 'You are not assigned to this grade and subject.'
                    ], 403);
                }
            }

            /* UPDATE QUESTION IMAGE */

            if ($request->hasFile('question_image')) {

                if ($question->question_image) {
                    Storage::disk('public')->delete($question->question_image);
                }

                $question->question_image = $request->file('question_image')
                    ->store('questions', 'public');
            }


            // update staus after rejection
            if (auth()->user()->role !== 'admin' && $question->status === 'rejected') {
                $question['status'] = 'pending';
                $question['approved_by'] = null;
                $question['approved_at'] = null;
                $question['rejection_reason'] = null;
            }

            /*UPDATE BASIC FIELDS*/
            $question->update($request->only([
                'grade_id',
                'subject_id',
                'lesson_id',
                'question',
                'type',
                'difficulty',
                'marks',
                'answer',
                'explanation',
                'status',
                'approved_by',
                'approved_at',
                'rejection_reason',
            ]));

            /*REPLACE OPTIONS*/

            if ($request->options) {

                // delete old options
                $question->options()->delete();

                foreach ($request->options as $index => $opt) {

                    $optionImage = null;

                    if ($request->hasFile("options.$index.option_image")) {

                        $optionImage = $request->file("options.$index.option_image")
                            ->store('question-options', 'public');
                    }

                    $question->options()->create([
                        'option_text' => $opt['option_text'] ?? null,
                        'option_image' => $optionImage,
                        'is_correct' => $opt['is_correct'] ?? false
                    ]);
                }
            }

            DB::commit();

            return response()->json([
                'message' => 'Question updated successfully',
                'data' => $question->load('options')
            ]);
        } catch (\Exception $e) {

            DB::rollback();

            return response()->json([
                'message' => 'Update failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /* DELETE QUESTION */


    public function destroy($id)
    {
        $question = Question::findOrFail($id);

        // delete images
        if ($question->question_image) {
            Storage::disk('public')->delete($question->question_image);
        }

        foreach ($question->options as $opt) {
            if ($opt->option_image) {
                Storage::disk('public')->delete($opt->option_image);
            }
        }

        $question->delete();

        return response()->json([
            'message' => 'Question deleted successfully'
        ]);
    }
}
