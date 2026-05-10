<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

use App\Models\Question;
use App\Models\QuestionPaper;
use App\Models\QuestionPaperQuestion;

class QuestionPaperController extends Controller
{

    public function index()
    {
        return QuestionPaper::with([
            'grade',
            'subject',
            'questions.question'
        ])
        ->latest()
        ->paginate(10);
    }


    public function store(Request $request)
    {
        DB::beginTransaction();



            $request->validate([

                'title' => 'required',
                'exam_type' => 'required',
                'duration' => 'required|numeric',
                'grade_id' => 'required',
                'subject_id' => 'required',
                'instructions' => 'required|min:150',
                'questions' => 'required|array|min:1',
                'questions.*.section' => 'nullable|string',
                'questions.*.instructions' => 'nullable|string'
            ]);


            $paper = QuestionPaper::create([

                'title' => $request->title,
                'exam_type' => $request->exam_type,
                'duration' => $request->duration,
                'instructions' => $request->instructions,
                'grade_id' => $request->grade_id,
                'subject_id' => $request->subject_id,

                'total_marks' => collect(
                    $request->questions
                )->sum('marks')

            ]);


            foreach ($request->questions as $index => $item) {

                QuestionPaperQuestion::create([

                    'question_paper_id' => $paper->id,
                    'question_id' => $item['question_id'],
                    'marks' => $item['marks'],
                    'sort_order' => $index + 1,
                    'section' => $item['section'] ?? null,
                    'instructions' => $item['instructions'] ?? null

                    ]);
            }

            DB::commit();

            return response()->json([
                'message' => 'Question paper created successfully',
                'data' => $paper->load(['questions.question'])
            ]);


    }



    public function show($id)
    {
        return QuestionPaper::with([
            'grade',
            'subject',
            'questions.question.options'

        ])->findOrFail($id);
    }


    public function update(Request $request, $id)
    {
        DB::beginTransaction();

        try {

            $paper = QuestionPaper::findOrFail($id);

            /* UPDATE PAPER */

            $paper->update([

                'title' => $request->title,
                'exam_type' => $request->exam_type,
                'duration' => $request->duration,
                'instructions' => $request->instructions,
                'grade_id' => $request->grade_id,
                'subject_id' => $request->subject_id,
                'total_marks' => collect(
                    $request->questions
                )->sum('marks')

            ]);

            /* DELETE OLD QUESTIONS */

            $paper->questions()->delete();

            /* INSERT NEW QUESTIONS */

            foreach ($request->questions as $index => $item) {

                QuestionPaperQuestion::create([

                    'question_paper_id' => $paper->id,
                    'question_id' => $item['question_id'],
                    'marks' => $item['marks'],
                    'sort_order' => $index + 1
                ]);
            }

            DB::commit();

            return response()->json([

                'message' => 'Question paper updated successfully',

                'data' => $paper->load([
                    'questions.question'
                ])
            ]);

        } catch (\Exception $e) {

            DB::rollback();

            return response()->json([

                'message' => 'Update failed',

                'error' => $e->getMessage()

            ], 500);
        }
    }

    /* DELETE PAPER    */

    public function destroy($id)
    {
        $paper = QuestionPaper::findOrFail($id);
        $paper->delete();
        return response()->json(['message' => 'Question paper deleted successfully']);
    }

    /* AUTO GENERATE PAPER */

    public function autoGenerate(Request $request)
    {

            $request->validate([
                'grade_id' => 'required',
                'subject_id' => 'required',
                'rules' => 'required|array'
            ]);

            $selectedQuestions = collect();

            /* APPLY RULES */

            foreach ($request->rules as $rule) {

                $query = Question::query()->where('grade_id',$request->grade_id)
                    ->where('subject_id',$request->subject_id);

                /* OPTIONAL LESSON */

                if ($request->lesson_id) {

                    $query->where('lesson_id',$request->lesson_id);
                }

                /* TYPE */

                if (!empty($rule['type'])) {
                    $query->where(
                        'type',
                        $rule['type']
                    );
                }

                /* DIFFICULTY */

                if (!empty($rule['difficulty'])) {
                    $query->where(
                        'difficulty',
                        $rule['difficulty']
                    );
                }

                /* RANDOM QUESTIONS */

                $questions = $query

                    ->inRandomOrder()
                    ->limit($rule['count'])
                    ->get();

                $selectedQuestions = $selectedQuestions->merge($questions);
            }

            /* UNIQUE QUESTIONS */

            $selectedQuestions = $selectedQuestions->unique('id');

            return response()->json($selectedQuestions->values());


    }
}
