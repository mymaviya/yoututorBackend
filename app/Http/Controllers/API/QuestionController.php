<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Question;
use App\Models\QuestionOption;
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
                'question' => 'required',
                'type' => 'required',
                'difficulty' => 'required',
                'marks' => 'required|numeric',
                'options' => 'nullable|array'
            ]);

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

            /* UPDATE QUESTION IMAGE */

            if ($request->hasFile('question_image')) {

                if ($question->question_image) {
                    Storage::disk('public')->delete($question->question_image);
                }

                $question->question_image = $request->file('question_image')
                    ->store('questions', 'public');
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
                'explanation'
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
