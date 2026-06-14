<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\PaperBlueprint;
use App\Models\Question;
use App\Models\QuestionPaper;
use App\Models\QuestionPaperQuestion;
use App\Models\QuestionTypeMaster;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class QuestionPaperController extends Controller
{
    public function index()
    {
        return QuestionPaper::with([
            'grade',
            'subject',
            'examName',
            'questions.question',
        ])
            ->latest()
            ->paginate(10);
    }

    private function normalize($value): string
    {
        return strtolower(trim((string) $value));
    }

    private function loadBlueprint($blueprintId): PaperBlueprint
    {
        return PaperBlueprint::with([
            'sections.questionType',
            'sections.bloomLevels',
        ])->findOrFail($blueprintId);
    }

    private function getQuestionsByIds(array $paperQuestions)
    {
        $ids = collect($paperQuestions)
            ->pluck('question_id')
            ->filter()
            ->unique()
            ->values();

        return Question::with('type')
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id');
    }

    private function questionTypeMatches($question, $blueprintSection): bool
    {
        return (int) $question->question_type_master_id === (int) $blueprintSection->question_type_master_id;
    }

    private function questionDifficultyMatches($question, $blueprintSection, bool $moderateMode): bool
    {
        if ($moderateMode) {
            return true;
        }

        if (!$blueprintSection->difficulty) {
            return true;
        }

        return $this->normalize($question->difficulty) === $this->normalize($blueprintSection->difficulty);
    }

    private function questionSectionMatches(array $paperQuestion, $blueprintSection): bool
    {
        return $this->normalize($paperQuestion['section'] ?? 'Section A') === $this->normalize($blueprintSection->section_name);
    }

    private function validateBlueprintCounts(PaperBlueprint $blueprint, array $paperQuestions, bool $moderateMode): array
    {
        $errors = [];
        $questions = $this->getQuestionsByIds($paperQuestions);

        foreach ($blueprint->sections as $blueprintSection) {
            $actual = collect($paperQuestions)->filter(function ($paperQuestion) use ($questions, $blueprintSection, $moderateMode) {
                $question = $questions->get($paperQuestion['question_id'] ?? null);

                if (!$question) {
                    return false;
                }

                return $this->questionSectionMatches($paperQuestion, $blueprintSection)
                    && $this->questionTypeMatches($question, $blueprintSection)
                    && $this->questionDifficultyMatches($question, $blueprintSection, $moderateMode);
            })->count();

            $required = (int) $blueprintSection->question_count;

            if ($actual !== $required) {
                $typeName = $blueprintSection->questionType?->name ?? 'Question Type';

                $errors[] = "{$blueprintSection->section_name} - {$typeName}: required {$required}, selected {$actual}";
            }
        }

        return $errors;
    }

    private function validateBloomDistribution(PaperBlueprint $blueprint, array $paperQuestions, bool $moderateMode): array
    {
        $errors = [];
        $questions = $this->getQuestionsByIds($paperQuestions);

        foreach ($blueprint->sections as $blueprintSection) {
            if ($blueprintSection->bloomLevels->isEmpty()) {
                continue;
            }

            $selectedForSection = collect($paperQuestions)->filter(function ($paperQuestion) use ($questions, $blueprintSection, $moderateMode) {
                $question = $questions->get($paperQuestion['question_id'] ?? null);

                if (!$question) {
                    return false;
                }

                return $this->questionSectionMatches($paperQuestion, $blueprintSection)
                    && $this->questionTypeMatches($question, $blueprintSection)
                    && $this->questionDifficultyMatches($question, $blueprintSection, $moderateMode);
            });

            $actualCounts = [];

            foreach ($selectedForSection as $paperQuestion) {
                $question = $questions->get($paperQuestion['question_id'] ?? null);

                if (!$question || !$question->bloom_level) {
                    continue;
                }

                $level = $this->normalize($question->bloom_level);

                $actualCounts[$level] = ($actualCounts[$level] ?? 0) + 1;
            }

            foreach ($blueprintSection->bloomLevels as $rule) {
                $level = $this->normalize($rule->bloom_level);
                $required = (int) $rule->calculated_count;
                $actual = (int) ($actualCounts[$level] ?? 0);

                if ($actual !== $required) {
                    $typeName = $blueprintSection->questionType?->name ?? 'Question Type';
                    $label = ucfirst($level);

                    $errors[] = "{$blueprintSection->section_name} - {$typeName} - {$label}: required {$required}, selected {$actual}";
                }
            }
        }

        return $errors;
    }

    private function validatePaperAgainstBlueprint(Request $request): ?array
    {
        if (!$request->filled('paper_blueprint_id')) {
            return null;
        }

        $blueprint = $this->loadBlueprint($request->paper_blueprint_id);
        $moderateMode = $request->boolean('moderate_mode');

        $countErrors = $this->validateBlueprintCounts(
            $blueprint,
            $request->questions ?? [],
            $moderateMode
        );

        if (!empty($countErrors)) {
            return [
                'message' => 'Blueprint validation failed.',
                'errors' => [
                    'blueprint' => $countErrors,
                ],
            ];
        }

        $bloomErrors = $this->validateBloomDistribution(
            $blueprint,
            $request->questions ?? [],
            $moderateMode
        );

        if (!empty($bloomErrors)) {
            return [
                'message' => 'Bloom distribution validation failed.',
                'errors' => [
                    'bloom' => $bloomErrors,
                ],
            ];
        }

        return null;
    }

    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required',
            'exam_type' => 'required',
            'duration' => 'required|numeric',
            'grade_id' => 'required',
            'subject_id' => 'required',
            'paper_blueprint_id' => 'required|exists:paper_blueprints,id',
            'moderate_mode' => 'nullable|boolean',
            'instructions' => 'required|min:150',
            'questions' => 'required|array|min:1',
            'questions.*.question_id' => 'required|exists:questions,id',
            'questions.*.marks' => 'nullable|numeric',
            'questions.*.section' => 'nullable|string',
            'questions.*.instructions' => 'nullable|string',
            'questions.*.sort_order' => 'nullable|integer',
        ]);

        $validationError = $this->validatePaperAgainstBlueprint($request);

        if ($validationError) {
            return response()->json($validationError, 422);
        }

        if ($request->user()->role === 'teacher') {
            $allowed = $request->user()
                ->assignments()
                ->where('grade_id', $request->grade_id)
                ->where('subject_id', $request->subject_id)
                ->exists();

            if (!$allowed) {
                return response()->json([
                    'message' => 'You are not assigned to this grade and subject.',
                ], 403);
            }
        }

        DB::beginTransaction();

        try {
            $paper = QuestionPaper::create([
                'title' => $request->title,
                'exam_type' => $request->exam_type,
                'duration' => $request->duration,
                'instructions' => $request->instructions,
                'grade_id' => $request->grade_id,
                'subject_id' => $request->subject_id,
                'paper_blueprint_id' => $request->paper_blueprint_id,
                'total_marks' => collect($request->questions)->sum(fn ($q) => (float) ($q['marks'] ?? 0)),
                'created_by' => auth()->id(),
            ]);

            foreach ($request->questions as $index => $item) {
                QuestionPaperQuestion::create([
                    'question_paper_id' => $paper->id,
                    'question_id' => $item['question_id'],
                    'marks' => $item['marks'] ?? 0,
                    'sort_order' => $item['sort_order'] ?? ($index + 1),
                    'section' => $item['section'] ?? 'Section A',
                    'instructions' => $item['instructions'] ?? null,
                ]);
            }

            DB::commit();

            AuditService::log(
                'QuestionPapers',
                'Create',
                'Question paper created ID: ' . $paper->id,
                null,
                $paper->toArray(),
                auth()->id()
            );

            return response()->json([
                'message' => 'Question paper created successfully',
                'data' => $paper->load(['questions.question']),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Create failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function show($id)
    {
        $paper = QuestionPaper::with([
            'grade',
            'subject',
            'questions.question.options',
            'questions.question.matchPairs',
            'questions.question.lesson',
            'questions.question.type',
        ])->findOrFail($id);

        $paper->questions->transform(function ($paperQuestion) {
            $question = $paperQuestion->question;

            if ($question && $question->type === 'match_column') {
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
        $request->validate([
            'title' => 'required',
            'exam_type' => 'required',
            'duration' => 'required|numeric',
            'grade_id' => 'required',
            'subject_id' => 'required',
            'paper_blueprint_id' => 'required|exists:paper_blueprints,id',
            'moderate_mode' => 'nullable|boolean',
            'instructions' => 'required|min:150',
            'questions' => 'required|array|min:1',
            'questions.*.question_id' => 'required|exists:questions,id',
            'questions.*.marks' => 'nullable|numeric',
            'questions.*.section' => 'nullable|string',
            'questions.*.instructions' => 'nullable|string',
            'questions.*.sort_order' => 'nullable|integer',
        ]);

        $validationError = $this->validatePaperAgainstBlueprint($request);

        if ($validationError) {
            return response()->json($validationError, 422);
        }

        DB::beginTransaction();

        try {
            $paper = QuestionPaper::findOrFail($id);

            if ($paper->status !== 'draft') {
                AuditService::log(
                    'QuestionPapers',
                    'Update',
                    'Only draft papers can be edited. Paper ID: ' . $paper->id,
                    $paper->toArray(),
                    null,
                    auth()->id()
                );

                return response()->json([
                    'message' => 'Only draft papers can be edited.',
                ], 422);
            }

            $paper->update([
                'title' => $request->title,
                'exam_type' => $request->exam_type,
                'duration' => $request->duration,
                'instructions' => $request->instructions,
                'grade_id' => $request->grade_id,
                'subject_id' => $request->subject_id,
                'paper_blueprint_id' => $request->paper_blueprint_id,
                'total_marks' => collect($request->questions)->sum(fn ($q) => (float) ($q['marks'] ?? 0)),
                'created_by' => auth()->id(),
            ]);

            $paper->questions()->delete();

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

            AuditService::log(
                'QuestionPapers',
                'Update',
                'Question paper updated ID: ' . $paper->id,
                null,
                $paper->toArray(),
                auth()->id()
            );

            return response()->json([
                'message' => 'Question paper updated successfully',
                'data' => $paper->load(['questions.question']),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Update failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function destroy($id)
    {
        $paper = QuestionPaper::findOrFail($id);

        if ($paper->status !== 'draft') {
            return response()->json([
                'message' => 'Only draft papers can be deleted.',
            ], 422);
        }

        $paper->delete();

        AuditService::log(
            'QuestionPapers',
            'Delete',
            'Question paper deleted ID: ' . $paper->id,
            $paper->toArray(),
            null,
            auth()->id()
        );

        return response()->json([
            'message' => 'Question paper deleted successfully',
        ]);
    }

    public function autoGenerate(Request $request)
    {
        $request->validate([
            'grade_id' => 'required',
            'subject_id' => 'required',
            'paper_blueprint_id' => 'nullable|exists:paper_blueprints,id',
            'lesson_id' => 'nullable',
            'moderate_mode' => 'nullable|boolean',
            'rules' => 'required|array',
            'rules.*.type' => 'nullable',
            'rules.*.question_type_master_id' => 'nullable',
            'rules.*.difficulty' => 'nullable|string',
            'rules.*.bloom_level' => 'nullable|string',
            'rules.*.count' => 'required|integer|min:1',
            'rules.*.section' => 'nullable|string',
            'rules.*.marks' => 'nullable|numeric',
        ]);

        $selectedQuestions = collect();
        $moderateMode = $request->boolean('moderate_mode');

        foreach ($request->rules as $rule) {
            $query = Question::query()
                ->where('grade_id', $request->grade_id)
                ->where('subject_id', $request->subject_id)
                ->where('status', 'approved');

            if ($request->filled('lesson_id')) {
                $query->where('lesson_id', $request->lesson_id);
            }

            if (!empty($rule['question_type_master_id'])) {
                $query->where('question_type_master_id', $rule['question_type_master_id']);
            } elseif (!empty($rule['type'])) {
                $typeId = QuestionTypeMaster::where('slug', $rule['type'])
                    ->orWhere('name', $rule['type'])
                    ->value('id');

                if ($typeId) {
                    $query->where('question_type_master_id', $typeId);
                }
            }

            if (!$moderateMode && !empty($rule['difficulty'])) {
                $query->where('difficulty', $rule['difficulty']);
            }

            if (!empty($rule['bloom_level'])) {
                $query->where('bloom_level', $rule['bloom_level']);
            }

            $questions = $query
                ->inRandomOrder()
                ->limit((int) $rule['count'])
                ->get()
                ->map(function ($question) use ($rule) {
                    $question->paper_section = $rule['section'] ?? 'Section A';
                    $question->paper_marks = $rule['marks'] ?? $question->marks ?? 0;

                    return $question;
                });

            $selectedQuestions = $selectedQuestions->merge($questions);
        }

        $selectedQuestions = $selectedQuestions->unique('id')->values();

        if ($request->filled('paper_blueprint_id')) {
            $paperQuestions = $selectedQuestions->map(function ($question) {
                return [
                    'question_id' => $question->id,
                    'section' => $question->paper_section ?? 'Section A',
                    'marks' => $question->paper_marks ?? $question->marks ?? 0,
                ];
            })->toArray();

            $blueprint = $this->loadBlueprint($request->paper_blueprint_id);

            $countErrors = $this->validateBlueprintCounts($blueprint, $paperQuestions, $moderateMode);

            if (!empty($countErrors)) {
                return response()->json([
                    'message' => 'Blueprint validation failed.',
                    'errors' => [
                        'blueprint' => $countErrors,
                    ],
                ], 422);
            }

            $bloomErrors = $this->validateBloomDistribution($blueprint, $paperQuestions, $moderateMode);

            if (!empty($bloomErrors)) {
                return response()->json([
                    'message' => 'Bloom distribution validation failed.',
                    'errors' => [
                        'bloom' => $bloomErrors,
                    ],
                ], 422);
            }
        }

        return response()->json($selectedQuestions);
    }

    public function finalize($id)
    {
        $paper = QuestionPaper::findOrFail($id);

        if ($paper->status !== 'draft') {
            return response()->json([
                'message' => 'Paper is already finalized.',
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
            'message' => 'Paper finalized successfully.',
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
            'message' => 'Paper reopened successfully.',
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
            'message' => 'Paper marked as printed.',
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
            'message' => 'Paper archived successfully',
        ]);
    }
}