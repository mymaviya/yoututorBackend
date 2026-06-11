<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

use App\Models\Question;
use App\Models\QuestionPaper;
use App\Models\QuestionPaperQuestion;
use App\Models\User;
use App\Services\AuditService;

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
            'paper_blueprint_id' => 'required|exists:paper_blueprints,id',
            'instructions' => 'required|min:150',
            'questions' => 'required|array|min:1',
            'questions.*.section' => 'nullable|string',
            'questions.*.instructions' => 'nullable|string'
        ]);

        if ($request->user()->role === 'teacher') {
            $allowed = $request->user()->assignments()
                ->where('grade_id', $request->grade_id)
                ->where('subject_id', $request->subject_id)
                ->exists();

            if (!$allowed) {
                return response()->json([
                    'message' => 'You are not assigned to this grade and subject.'
                ], 403);
            }
        }

        $blueprintErrors = $this->validatePaperAgainstBlueprint(
            $request->paper_blueprint_id,
            $request->questions,
            (bool) $request->moderate_difficulty_mode
        );

        if (!empty($blueprintErrors)) {
            DB::rollBack();

            return response()->json([
                'message' => 'Paper does not follow the selected blueprint.',
                'errors' => [
                    'blueprint' => $blueprintErrors,
                ],
            ], 422);
        }

        $paper = QuestionPaper::create([

            'title' => $request->title,
            'exam_type' => $request->exam_type,
            'duration' => $request->duration,
            'instructions' => $request->instructions,
            'grade_id' => $request->grade_id,
            'subject_id' => $request->subject_id,
            'paper_blueprint_id' => $request->paper_blueprint_id,
            'total_marks' => collect($request->questions)->sum('marks'),
            'created_by' => auth()->id(),
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

        AuditService::log('QuestionPapers', 'Create', 'Question paper created ID: ' . $paper->id, null, $paper->toArray(), auth()->id());

        return response()->json([
            'message' => 'Question paper created successfully',
            'data' => $paper->load(['questions.question'])
        ]);
    }



    public function show($id)
    {
        $paper = QuestionPaper::with([
            'grade',
            'subject',
            'questions.question.options',
            'questions.question.matchPairs',
            'questions.question.lesson',
            'questions.question.options',
            'questions.question.questionType',

        ])->findOrFail($id);

        $paper->questions->transform(function ($paperQuestion) {

            $question = $paperQuestion->question;

            if ($question->type === 'match_column') {

                $question->left_column = $question->matchPairs
                    ->shuffle()
                    ->values()
                    ->map(function ($pair, $index) {

                        return [
                            'id' => $pair->id,
                            'label' => $index + 1,
                            'text' => $pair->left_text,
                        ];
                    });

                $question->right_column = $question->matchPairs
                    ->shuffle()
                    ->values()
                    ->map(function ($pair, $index) {

                        return [
                            'id' => $pair->id,
                            'label' => chr(65 + $index),
                            'text' => $pair->right_text,
                        ];
                    });
            }

            return $paperQuestion;
        });

        return response()->json($paper);
    }


    public function update(Request $request, $id)
    {
        DB::beginTransaction();

        try {

            $paper = QuestionPaper::findOrFail($id);

            if ($paper->status !== 'draft') {
                AuditService::log('QuestionPapers', 'Update', 'Only draft papers can be edited. Paper ID: ' . $paper->id, $paper->toArray(), null, auth()->id());
                return response()->json([
                    'message' => 'Only draft papers can be edited.'
                ], 422);
            }

            $blueprintErrors = $this->validatePaperAgainstBlueprint(
                $request->paper_blueprint_id,
                $request->questions,
                (bool) $request->moderate_difficulty_mode
            );

            if (!empty($blueprintErrors)) {
                DB::rollBack();

                return response()->json([
                    'message' => 'Paper does not follow the selected blueprint.',
                    'errors' => [
                        'blueprint' => $blueprintErrors,
                    ],
                ], 422);
            }

            /* UPDATE PAPER */

            $paper->update([

                'title' => $request->title,
                'exam_type' => $request->exam_type,
                'duration' => $request->duration,
                'instructions' => $request->instructions,
                'grade_id' => $request->grade_id,
                'subject_id' => $request->subject_id,
                'paper_blueprint_id' => $request->paper_blueprint_id,
                'total_marks' => collect($request->questions)->sum(fn($q) => (float) ($q['marks'] ?? 0)),
                'created_by' => auth()->id(),
            ]);

            /* DELETE OLD QUESTIONS */

            $paper->questions()->delete();

            /* INSERT NEW QUESTIONS */

            foreach ($request->questions as $index => $item) {

                QuestionPaperQuestion::create([
                    'question_paper_id' => $paper->id,
                    'question_id' => $item['question_id'],
                    'marks' => $item['marks'] ?? 0,
                    'section' => $item['section'] ?? 'Section A',
                    'instructions' => $item['instructions'] ?? null,
                    'sort_order' => $item['sort_order'] ?? ($index + 1),
                ]);
            }

            DB::commit();

            AuditService::log('QuestionPapers', 'Update', 'Question paper updated ID: ' . $paper->id, null, $paper->toArray(), auth()->id());

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

    /* DELETE PAPER   */

    public function destroy($id)
    {
        $paper = QuestionPaper::findOrFail($id);

        if ($paper->status !== 'draft') {
            return response()->json([
                'message' => 'Only draft papers can be deleted.'
            ], 422);
        }

        $paper->delete();

        AuditService::log('QuestionPapers', 'Delete', 'Question paper deleted ID: ' . $paper->id, $paper->toArray(), null, auth()->id());
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

            $query = Question::query()->where('grade_id', $request->grade_id)
                ->where('subject_id', $request->subject_id);

            /* OPTIONAL LESSON */

            if ($request->lesson_id) {

                $query->where('lesson_id', $request->lesson_id);
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

    public function finalize($id)
    {
        $paper = QuestionPaper::findOrFail($id);

        if ($paper->status !== 'draft') {
            return response()->json([
                'message' => 'Paper is already finalized.'
            ], 422);
        }

        $paper->update([
            'status' => 'finalized',
            'finalized_at' => now(),
            'finalized_by' => auth()->id(),
        ]);

        AuditService::log(
            'QuestionPapers',
            'Finalize',
            'Question paper finalized ID: ' . $paper->id,
            null,
            $paper->toArray(),
            auth()->id()
        );

        return response()->json([
            'message' => 'Paper finalized successfully.'
        ]);
    }

    public function reopen($id)
    {
        $paper = QuestionPaper::findOrFail($id);

        $paper->update([
            'status' => 'draft',
            'finalized_at' => null,
            'finalized_by' => null,
        ]);

        AuditService::log(
            'QuestionPapers',
            'Reopen',
            'Question paper reopened ID: ' . $paper->id,
            null,
            $paper->toArray(),
            auth()->id()
        );

        return response()->json([
            'message' => 'Paper reopened successfully.'
        ]);
    }

    public function markPrinted($id)
    {
        $paper = QuestionPaper::findOrFail($id);

        $paper->update([
            'status' => 'printed',
            'printed_at' => now(),
            'printed_by' => auth()->id(),
        ]);

        return response()->json([
            'message' => 'Paper marked as printed.'
        ]);
    }

    public function archive($id)
    {
        $paper = QuestionPaper::findOrFail($id);

        $paper->update([
            'status' => 'archived',
            'archived_at' => now(),
            'archived_by' => auth()->id(),
        ]);

        return response()->json([
            'message' => 'Paper archived successfully.'
        ]);
    }

    private function validatePaperAgainstBlueprint($paperBlueprintId, array $questions, bool $moderateDifficultyMode = false): array
    {
        $blueprint = \App\Models\PaperBlueprint::with([
            'sections.questionTypeMaster',
            'sections.bloomLevels',
        ])->findOrFail($paperBlueprintId);

        $errors = [];

        $paperQuestions = collect($questions);

        foreach ($blueprint->sections as $section) {
            $requiredCount = (int) $section->question_count;
            $requiredMarks = (float) $section->marks_per_question;
            $requiredTypeId = (int) $section->question_type_master_id;
            $requiredDifficulty = $section->difficulty;

            $matchingQuestions = $paperQuestions->filter(function ($item) use (
                $section,
                $requiredTypeId,
                $requiredDifficulty,
                $moderateDifficultyMode
            ) {
                $question = \App\Models\Question::find($item['question_id'] ?? null);

                if (!$question) {
                    return false;
                }

                if (($item['section'] ?? null) !== $section->section_name) {
                    return false;
                }

                if ((int) $question->question_type_master_id !== $requiredTypeId) {
                    return false;
                }

                if (
                    !$moderateDifficultyMode &&
                    $requiredDifficulty &&
                    $question->difficulty !== $requiredDifficulty
                ) {
                    return false;
                }

                return true;
            });

            if ($matchingQuestions->count() !== $requiredCount) {
                $errors[] = "{$section->section_name}: {$section->questionTypeMaster->name} requires {$requiredCount} question(s), but {$matchingQuestions->count()} given.";
            }

            foreach ($matchingQuestions as $item) {
                if ((float) ($item['marks'] ?? 0) !== $requiredMarks) {
                    $errors[] = "{$section->section_name}: marks must be {$requiredMarks} for {$section->questionTypeMaster->name}.";
                }
            }

            if ($section->bloomLevels && $section->bloomLevels->count()) {
                foreach ($section->bloomLevels as $bloomRule) {
                    $bloomCount = $matchingQuestions->filter(function ($item) use ($bloomRule) {
                        $question = \App\Models\Question::find($item['question_id'] ?? null);

                        return $question && $question->bloom_level === $bloomRule->bloom_level;
                    })->count();

                    if ($bloomCount !== (int) $bloomRule->question_count) {
                        $errors[] = "{$section->section_name}: Bloom level {$bloomRule->bloom_level} requires {$bloomRule->question_count} question(s), but {$bloomCount} given.";
                    }
                }
            }
        }

        $expectedTotal = (float) $blueprint->sections->sum(function ($section) {
            return (int) $section->question_count * (float) $section->marks_per_question;
        });

        $actualTotal = (float) $paperQuestions->sum(function ($item) {
            return (float) ($item['marks'] ?? 0);
        });

        if ($expectedTotal !== $actualTotal) {
            $errors[] = "Total marks mismatch. Blueprint requires {$expectedTotal}, but paper has {$actualTotal}.";
        }

        return $errors;
    }
}
