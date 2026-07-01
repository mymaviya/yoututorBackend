<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\GenerateAiPaperJob;
use App\Models\AiGeneratedQuestion;
use App\Models\AiPaperGeneration;
use App\Models\PaperBlueprint;
use App\Models\Question;
use App\Models\QuestionMatchPair;
use App\Models\QuestionOption;
use App\Models\QuestionPaper;
use App\Models\QuestionPaperItem;
use App\Models\QuestionPaperQuestion;
use App\Services\AiPaperGeneratorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AiPaperGeneratorController extends Controller
{
    public function index(Request $request)
    {
        $query = AiPaperGeneration::with([
            'blueprint',
            'creator',
            'examName',
            'examPortion',
        ])
            ->where('subscription_id', auth()->user()->subscription_id);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        return response()->json([
            'success' => true,
            'data' => $query
                ->latest()
                ->paginate((int) $request->input('per_page', 20)),
        ]);
    }

    public function store(Request $request, AiPaperGeneratorService $service)
    {
        set_time_limit(600);
        ini_set('max_execution_time', 600);

        $data = $request->validate([
            'paper_blueprint_id' => ['required', 'exists:paper_blueprints,id'],
            'title' => ['nullable', 'string', 'max:255'],
            'language' => ['nullable', 'string', 'max:20'],
            'difficulty' => ['nullable', 'string', 'max:50'],
            'exam_name_id' => ['required', 'exists:exam_names,id'],
            'exam_portion_id' => ['required', 'exists:exam_portions,id'],
        ]);

        $subscriptionId = auth()->user()->subscription_id;

        $blueprint = PaperBlueprint::where('id', $data['paper_blueprint_id'])
            ->where('subscription_id', $subscriptionId)
            ->firstOrFail();

        $generation = AiPaperGeneration::create([
            'subscription_id' => $subscriptionId,
            'paper_blueprint_id' => $blueprint->id,
            'exam_name_id' => $data['exam_name_id'],
            'exam_portion_id' => $data['exam_portion_id'],
            'created_by' => auth()->id(),
            'title' => $data['title'] ?? $blueprint->name,
            'status' => 'draft',
            'language' => $data['language'] ?? 'en',
            'difficulty' => $data['difficulty'] ?? null,
        ]);

        try {
            GenerateAiPaperJob::dispatch($generation->id);

            return response()->json([
                'success' => true,
                'message' => 'AI paper generation started.',
                'data' => $generation->fresh(),
            ], 201);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ], 500);
        }
    }

    public function show(AiPaperGeneration $aiPaperGeneration)
    {
        $this->ensureAccess($aiPaperGeneration);

        return response()->json([
            'success' => true,
            'data' => $aiPaperGeneration->load([
                'blueprint',
                'creator',
                'examName',
                'examPortion',
                'questions.grade',
                'questions.stream',
                'questions.subject',
                'questions.lesson',
                'questions.type',
            ]),
        ]);
    }

    public function destroy(AiPaperGeneration $aiPaperGeneration)
    {
        $this->ensureAccess($aiPaperGeneration);

        $aiPaperGeneration->delete();

        return response()->json([
            'success' => true,
            'message' => 'AI generation deleted successfully.',
        ]);
    }

    public function saveToQuestionBank(Request $request, AiPaperGeneration $aiPaperGeneration)
    {
        $this->ensureAccess($aiPaperGeneration);

        $data = $request->validate([
            'ai_generated_question_ids' => ['nullable', 'array'],
            'ai_generated_question_ids.*' => ['exists:ai_generated_questions,id'],
        ]);

        $query = $aiPaperGeneration->questions()
            ->where('is_selected', true);

        if (! empty($data['ai_generated_question_ids'])) {
            $query->whereIn('id', $data['ai_generated_question_ids']);
        }

        $questions = $query
            ->orderBy('section_index')
            ->orderBy('sort_order')
            ->get();

        if ($questions->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No selected AI questions found.',
            ], 422);
        }

        $created = 0;
        $skipped = 0;
        $paper = null;

        DB::transaction(function () use ($aiPaperGeneration, $questions, &$created, &$skipped, &$paper) {
            $aiPaperGeneration->loadMissing([
                'blueprint.sections',
                'examName',
            ]);

            $blueprint = $aiPaperGeneration->blueprint;

            $paper = QuestionPaper::firstOrCreate(
                [
                    'subscription_id' => $aiPaperGeneration->subscription_id,
                    'ai_paper_generation_id' => $aiPaperGeneration->id,
                ],
                [
                    'grade_id' => $blueprint?->grade_id,
                    'stream_id' => $blueprint?->stream_id,
                    'subject_id' => $blueprint?->subject_id,
                    'exam_name_id' => $aiPaperGeneration->exam_name_id ?? $blueprint?->exam_name_id,
                    'paper_blueprint_id' => $aiPaperGeneration->paper_blueprint_id,
                    'title' => $aiPaperGeneration->title ?: ($blueprint?->name ?? 'AI Generated Paper'),
                    'instructions' => $blueprint?->instructions,
                    'total_marks' => 0,
                    'duration_minutes' => $blueprint?->duration_minutes,
                    'status' => 'draft',
                    'created_by' => auth()->id(),
                    'is_ai_generated' => true,
                ]
            );

            if (! $paper->is_ai_generated) {
                $paper->update([
                    'is_ai_generated' => true,
                    'ai_paper_generation_id' => $aiPaperGeneration->id,
                ]);
            }

            foreach ($questions as $generated) {
                $question = null;

                if ($generated->question_id) {
                    $question = Question::where('subscription_id', $generated->subscription_id)
                        ->where('id', $generated->question_id)
                        ->first();
                }

                if (! $question) {
                    $question = Question::where('subscription_id', $generated->subscription_id)
                        ->where('grade_id', $generated->grade_id)
                        ->where('stream_id', $generated->stream_id)
                        ->where('subject_id', $generated->subject_id)
                        ->where('lesson_id', $generated->lesson_id)
                        ->where('question_type_master_id', $generated->question_type_master_id)
                        ->where('question', $generated->question)
                        ->first();
                }

                if ($question) {
                    $skipped++;
                } else {
                    $question = Question::create([
                        'subscription_id' => $generated->subscription_id,
                        'grade_id' => $generated->grade_id,
                        'stream_id' => $generated->stream_id,
                        'subject_id' => $generated->subject_id,
                        'lesson_id' => $generated->lesson_id,
                        'question_type_master_id' => $generated->question_type_master_id,
                        'question' => $generated->question,
                        'difficulty' => $generated->difficulty,
                        'bloom_level' => $generated->bloom_level,
                        'marks' => $generated->marks,
                        'answer' => $generated->answer,
                        'explanation' => $generated->explanation,
                        'status' => 'approved',
                        'approved_by' => auth()->id(),
                        'approved_at' => now(),
                        'created_by' => auth()->id(),
                        'is_active' => true,
                        'is_ai_generated' => true,
                        'ai_generated_question_id' => $generated->id,
                        'ai_paper_generation_id' => $aiPaperGeneration->id,
                    ]);

                    foreach (($generated->options ?? []) as $option) {
                        QuestionOption::create([
                            'question_id' => $question->id,
                            'option_text' => $option['option_text'] ?? '',
                            'is_correct' => (bool) ($option['is_correct'] ?? false),
                            'sort_order' => (int) ($option['sort_order'] ?? 0),
                        ]);
                    }

                    foreach (($generated->match_pairs ?? []) as $pair) {
                        QuestionMatchPair::create([
                            'question_id' => $question->id,
                            'left_value' => $pair['left_value'] ?? '',
                            'right_value' => $pair['right_value'] ?? '',
                            'sort_order' => (int) ($pair['sort_order'] ?? 0),
                        ]);
                    }

                    $created++;
                }

                $sectionName = $this->resolveSectionName($aiPaperGeneration, $generated);

                QuestionPaperQuestion::updateOrCreate(
                    [
                        'question_paper_id' => $paper->id,
                        'question_id' => $question->id,
                    ],
                    [
                        'marks' => $generated->marks,
                        'section' => $sectionName,
                        'instructions' => null,
                        'sort_order' => $generated->sort_order,
                        'is_ai_generated' => true,
                        'ai_generated_question_id' => $generated->id,
                    ]
                );

                QuestionPaperItem::updateOrCreate(
                    [
                        'question_paper_id' => $paper->id,
                        'question_id' => $question->id,
                    ],
                    [
                        'section' => $sectionName,
                        'display_order' => $generated->sort_order,
                        'is_optional' => false,
                        'group_no' => null,
                        'is_ai_generated' => true,
                        'ai_generated_question_id' => $generated->id,
                    ]
                );

                $generated->update([
                    'saved_to_question_bank' => true,
                    'question_id' => $question->id,
                ]);
            }

            $paper->update([
                'total_marks' => $paper->questions()->sum('marks'),
            ]);

            $aiPaperGeneration->update([
                'status' => 'converted',
                'question_paper_id' => $paper->id,
            ]);
        });

        return response()->json([
            'success' => true,
            'message' => 'Generated questions saved to Question Bank and AI question paper created.',
            'created' => $created,
            'skipped' => $skipped,
            'question_paper_id' => $paper?->id,
        ]);
    }

    public function progress(AiPaperGeneration $aiPaperGeneration)
    {
        $this->ensureAccess($aiPaperGeneration);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $aiPaperGeneration->id,
                'status' => $aiPaperGeneration->status,
                'progress_percentage' => $aiPaperGeneration->progress_percentage,
                'current_section' => $aiPaperGeneration->current_section,
                'progress_message' => $aiPaperGeneration->progress_message,
                'error_message' => $aiPaperGeneration->error_message,
                'total_questions' => $aiPaperGeneration->total_questions,
                'total_marks' => $aiPaperGeneration->total_marks,
            ],
        ]);
    }

    public function regenerateQuestion(
        AiGeneratedQuestion $aiGeneratedQuestion,
        AiPaperGeneratorService $service
    ) {
        return $this->regenerateQuestionPreview($aiGeneratedQuestion, $service);
    }

    public function regenerateQuestionPreview(
        AiGeneratedQuestion $aiGeneratedQuestion,
        AiPaperGeneratorService $service
    ) {
        $generation = $aiGeneratedQuestion->generation;

        $this->ensureAccess($generation);

        try {
            $question = $service->regenerateQuestionPreview(
                $aiGeneratedQuestion
            );

            return response()->json([
                'success' => true,
                'message' => 'Regenerated question preview created.',
                'data' => $question,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ], 500);
        }
    }

    public function acceptRegeneratedQuestion(
        AiGeneratedQuestion $aiGeneratedQuestion,
        AiPaperGeneratorService $service
    ) {
        $generation = $aiGeneratedQuestion->generation;

        $this->ensureAccess($generation);

        try {
            $question = $service->acceptRegeneratedQuestion(
                $aiGeneratedQuestion
            );

            return response()->json([
                'success' => true,
                'message' => 'Regenerated question accepted.',
                'data' => $question,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ], 500);
        }
    }

    private function resolveSectionName(AiPaperGeneration $generation, AiGeneratedQuestion $question): ?string
    {
        $section = $generation->blueprint?->sections
            ?->values()
            ->get((int) ($question->section_index ?? 0));

        return $section?->section_name
            ?? ($question->section_index !== null ? 'Section ' . ((int) $question->section_index + 1) : null);
    }

    private function ensureAccess(AiPaperGeneration $generation): void
    {
        abort_if(
            (int) $generation->subscription_id !== (int) auth()->user()->subscription_id,
            403,
            'You are not allowed to access this AI generation.'
        );
    }
}
