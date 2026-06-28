<?php

namespace App\Services;

use App\Models\AiGeneratedQuestion;
use App\Models\AiPaperGeneration;
use App\Models\ExamPortion;
use App\Models\PaperBlueprint;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class AiPaperGeneratorService
{
    public function generate(AiPaperGeneration $generation): AiPaperGeneration
    {
        $generation->update([
            'status' => 'generating',
            'error_message' => null,
        ]);

        try {
            $blueprint = PaperBlueprint::with([
                'grade',
                'stream',
                'subject',
                'examName',
                'sections.questionType',
                'sections.bloomLevels',
            ])->findOrFail($generation->paper_blueprint_id);

            $examPortion = $this->resolveExamPortion($generation, $blueprint);

            $prompt = $this->buildPrompt($blueprint, $generation, $examPortion);

            $generation->update([
                'prompt' => $prompt,
            ]);

            $response = $this->callAi($prompt);

            $generation->update([
                'ai_response' => json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
            ]);

            $questions = $this->normalizeQuestions($response);

            if (empty($questions)) {
                throw new RuntimeException('AI did not return any valid questions.');
            }

            $this->validateQuestionsAgainstBlueprint($questions, $blueprint);
            $this->validateQuestionsAgainstSyllabus($questions, $examPortion);

            $generation->questions()->delete();

            $totalMarks = 0;
            $totalQuestions = 0;

            foreach ($questions as $index => $item) {
                $marks = (float) ($item['marks'] ?? 1);

                AiGeneratedQuestion::create([
                    'ai_paper_generation_id' => $generation->id,
                    'subscription_id' => $generation->subscription_id,

                    'grade_id' => $blueprint->grade_id,
                    'stream_id' => $blueprint->stream_id,
                    'subject_id' => $blueprint->subject_id,
                    'lesson_id' => $item['lesson_id'] ?? null,
                    'question_type_master_id' => $item['question_type_master_id'] ?? null,

                    'question' => $item['question'],
                    'answer' => $item['answer'] ?? null,
                    'explanation' => $item['explanation'] ?? null,

                    'difficulty' => $item['difficulty'] ?? $generation->difficulty ?? 'medium',
                    'bloom_level' => $item['bloom_level'] ?? null,
                    'marks' => $marks,

                    'options' => $item['options'] ?? null,
                    'match_pairs' => $item['match_pairs'] ?? null,

                    'section_index' => (int) ($item['section_index'] ?? 0),
                    'sort_order' => $index + 1,

                    'is_selected' => true,
                    'saved_to_question_bank' => false,
                ]);

                $totalMarks += $marks;
                $totalQuestions++;
            }

            $generation->update([
                'status' => 'generated',
                'total_questions' => $totalQuestions,
                'total_marks' => $totalMarks,
            ]);

            return $generation->fresh([
                'blueprint',
                'questions.type',
                'questions.lesson',
            ]);
        } catch (\Throwable $e) {
            Log::error('AI paper generation failed', [
                'generation_id' => $generation->id,
                'message' => $e->getMessage(),
            ]);

            $generation->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    private function resolveExamPortion(AiPaperGeneration $generation, PaperBlueprint $blueprint): ?ExamPortion
    {
        $examPortionId = $generation->exam_portion_id ?? null;
        $examNameId = $generation->exam_name_id ?? $blueprint->exam_name_id ?? null;

        $query = ExamPortion::with([
            'examName',
            'grade',
            'stream',
            'subject',
            'lessons.lesson',
        ])
            ->where('subscription_id', $generation->subscription_id)
            ->where('grade_id', $blueprint->grade_id)
            ->where('subject_id', $blueprint->subject_id)
            ->where('status', 'approved');

        if ($blueprint->stream_id) {
            $query->where(function ($q) use ($blueprint) {
                $q->whereNull('stream_id')
                    ->orWhere('stream_id', $blueprint->stream_id);
            });
        }

        if ($examPortionId) {
            $query->where('id', $examPortionId);
        } elseif ($examNameId) {
            $query->where('exam_name_id', $examNameId);
        } else {
            return null;
        }

        $portion = $query->latest('approved_at')->first();

        if (! $portion) {
            throw new RuntimeException('Approved exam syllabus portion not found for this blueprint/exam.');
        }

        if ($portion->lessons->isEmpty()) {
            throw new RuntimeException('Approved exam syllabus has no lessons.');
        }

        return $portion;
    }

    public function buildPrompt(
        PaperBlueprint $blueprint,
        AiPaperGeneration $generation,
        ?ExamPortion $examPortion = null
    ): string {
        $sections = ($blueprint->sections ?? collect())
            ->map(function ($section, $index) {
                $bloomDistribution = ($section->bloomLevels ?? collect())
                    ->map(function ($bloom) {
                        return [
                            'bloom_level' => $bloom->bloom_level,
                            'percentage' => (float) $bloom->percentage,
                            'calculated_count' => (int) $bloom->calculated_count,
                        ];
                    })
                    ->values()
                    ->all();

                return [
                    'section_index' => $index,
                    'section_id' => $section->id,
                    'section_name' => $section->section_name,
                    'question_type_master_id' => $section->question_type_master_id,
                    'question_type' => $section->questionType?->name ?? $section->questionType?->slug,
                    'difficulty' => $section->difficulty ?? 'medium',
                    'question_count' => (int) ($section->question_count ?? 1),
                    'marks_each' => (float) ($section->marks_per_question ?? 1),
                    'total_section_marks' => (float) (($section->question_count ?? 1) * ($section->marks_per_question ?? 1)),
                    'instructions' => $section->instructions,
                    'bloom_distribution' => $bloomDistribution,
                ];
            })
            ->values()
            ->all();

        if (empty($sections)) {
            throw new RuntimeException('Blueprint has no sections configured.');
        }

        $syllabusLessons = $this->buildSyllabusLessons($examPortion);

        if (empty($syllabusLessons)) {
            throw new RuntimeException('Exam syllabus lessons are required for AI paper generation.');
        }

        $payload = [
            'instruction' => 'Generate a question paper strictly following the blueprint rules and exam syllabus. Return only valid JSON. Do not include markdown.',
            'output_schema' => [
                'questions' => [
                    [
                        'section_index' => 'integer',
                        'lesson_id' => 'integer',
                        'lesson_name' => 'string',
                        'question_type_master_id' => 'integer|null',
                        'question_type' => 'string',
                        'question' => 'string',
                        'answer' => 'string|null',
                        'explanation' => 'string|null',
                        'difficulty' => 'easy|medium|hard',
                        'bloom_level' => 'remember|understand|apply|analyze|evaluate|create|null',
                        'marks' => 'number',
                        'options' => [
                            [
                                'option_text' => 'string',
                                'is_correct' => 'boolean',
                            ],
                        ],
                        'match_pairs' => [
                            [
                                'left_value' => 'string',
                                'right_value' => 'string',
                            ],
                        ],
                    ],
                ],
            ],
            'paper' => [
                'title' => $generation->title,
                'language' => $generation->language,
                'difficulty' => $generation->difficulty,
                'grade' => $blueprint->grade?->name,
                'stream' => $blueprint->stream?->name,
                'subject' => $blueprint->subject?->name,
                'exam_name' => $examPortion?->examName?->name ?? $blueprint->examName?->name ?? null,
                'blueprint_name' => $blueprint->name,
                'duration_minutes' => $blueprint->duration_minutes,
                'total_marks' => (float) $blueprint->total_marks,
            ],
            'exam_syllabus' => [
                'exam_portion_id' => $examPortion?->id,
                'exam_name_id' => $examPortion?->exam_name_id ?? $blueprint->exam_name_id ?? null,
                'status' => $examPortion?->status,
                'lessons' => $syllabusLessons,
            ],
            'blueprint_sections' => $sections,
            'rules' => [
                'Generate EXACTLY question_count questions for every blueprint section.',
                'Never generate fewer questions than question_count.',
                'Never generate more questions than question_count.',
                'If a section requires 10 MCQ questions, return exactly 10 MCQ questions for that section.',
                'Use the same section_index returned in blueprint_sections.',
                'Use the same question_type_master_id returned in blueprint_sections.',
                'Marks must exactly match marks_each from the blueprint section.',
                'Use the difficulty from each blueprint section unless the paper difficulty is stricter.',
                'Follow bloom_distribution if provided. Use calculated_count as the exact target count for each bloom level.',
                'Every question must come only from exam_syllabus.lessons.',
                'Every question must include one valid lesson_id from exam_syllabus.lessons.',
                'Do not generate any question from outside the listed lesson names, topics, or learning objectives.',
                'For MCQ questions, provide exactly 4 options and exactly one correct option.',
                'For match_column questions, provide match_pairs.',
                'Do not repeat questions.',
                'Do not use copyrighted textbook passages.',
                'Return only JSON with a questions array.',
            ],
        ];

        return json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    private function buildSyllabusLessons(?ExamPortion $examPortion): array
    {
        if (! $examPortion) {
            return [];
        }

        return ($examPortion->lessons ?? collect())
            ->filter(fn ($portionLesson) => $portionLesson->lesson)
            ->map(function ($portionLesson) {
                return [
                    'lesson_id' => $portionLesson->lesson_id,
                    'lesson_name' => $portionLesson->lesson?->name,
                    'topics' => $portionLesson->topics,
                    'learning_objectives' => $portionLesson->learning_objectives,
                    'remarks' => $portionLesson->remarks,
                ];
            })
            ->values()
            ->all();
    }

    private function callAi(string $prompt): array
    {
        $apiKey = config('services.openai.api_key');

        if (! $apiKey) {
            throw new RuntimeException('OpenAI API key is not configured.');
        }

        $model = config('services.openai.model', 'gpt-4o-mini');

        $response = Http::withToken($apiKey)
            ->timeout(120)
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => $model,
                'temperature' => 0.2,
                'response_format' => [
                    'type' => 'json_object',
                ],
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'You are an expert school assessment question paper creator. Return only valid JSON. Follow blueprint counts exactly. Do not skip any section.',
                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt,
                    ],
                ],
            ]);

        if ($response->failed()) {
            throw new RuntimeException(
                'AI request failed: ' . $response->body()
            );
        }

        $content = $response->json('choices.0.message.content');

        if (! $content) {
            throw new RuntimeException('AI response was empty.');
        }

        $decoded = json_decode($content, true);

        if (! is_array($decoded)) {
            throw new RuntimeException('AI response was not valid JSON.');
        }

        return $decoded;
    }

    private function normalizeQuestions(array $response): array
    {
        $questions = $response['questions'] ?? [];

        if (! is_array($questions)) {
            return [];
        }

        return collect($questions)
            ->filter(fn ($item) => is_array($item) && ! empty($item['question']))
            ->map(function ($item) {
                return [
                    'section_index' => (int) Arr::get($item, 'section_index', 0),
                    'lesson_id' => Arr::get($item, 'lesson_id'),
                    'question_type_master_id' => Arr::get($item, 'question_type_master_id'),
                    'question' => trim((string) Arr::get($item, 'question')),
                    'answer' => Arr::get($item, 'answer'),
                    'explanation' => Arr::get($item, 'explanation'),
                    'difficulty' => Arr::get($item, 'difficulty', 'medium'),
                    'bloom_level' => Arr::get($item, 'bloom_level'),
                    'marks' => (float) Arr::get($item, 'marks', 1),
                    'options' => $this->normalizeOptions(Arr::get($item, 'options')),
                    'match_pairs' => $this->normalizeMatchPairs(Arr::get($item, 'match_pairs')),
                ];
            })
            ->values()
            ->all();
    }

    private function validateQuestionsAgainstBlueprint(array $questions, PaperBlueprint $blueprint): void
    {
        $sections = $blueprint->sections->values();

        $expectedTotal = $sections->sum(fn ($section) => (int) $section->question_count);
        $actualTotal = count($questions);

        if ($actualTotal !== $expectedTotal) {
            throw new RuntimeException(
                "Blueprint requires {$expectedTotal} total questions, but AI generated {$actualTotal}."
            );
        }

        foreach ($sections as $index => $section) {
            $expectedCount = (int) $section->question_count;

            $sectionQuestions = collect($questions)
                ->where('section_index', $index)
                ->values();

            $actualCount = $sectionQuestions->count();

            if ($actualCount !== $expectedCount) {
                $typeName = $section->questionType?->name
                    ?? $section->section_name
                    ?? 'Section ' . ($index + 1);

                throw new RuntimeException(
                    "{$typeName} requires {$expectedCount} questions, but AI generated {$actualCount}."
                );
            }

            foreach ($sectionQuestions as $question) {
                if (
                    ! empty($section->question_type_master_id) &&
                    (int) ($question['question_type_master_id'] ?? 0) !== (int) $section->question_type_master_id
                ) {
                    throw new RuntimeException(
                        'AI generated wrong question type in section ' . ($index + 1) . '.'
                    );
                }

                $expectedMarks = (float) ($section->marks_per_question ?? 1);
                $actualMarks = (float) ($question['marks'] ?? 0);

                if ($actualMarks !== $expectedMarks) {
                    throw new RuntimeException(
                        'AI generated wrong marks in section ' . ($index + 1) . '. Expected ' . $expectedMarks . ', got ' . $actualMarks . '.'
                    );
                }

                $typeName = strtolower((string) ($section->questionType?->name ?? $section->questionType?->slug ?? ''));

                if (
                    str_contains($typeName, 'mcq') ||
                    str_contains($typeName, 'multiple')
                ) {
                    $options = $question['options'] ?? [];

                    if (! is_array($options) || count($options) !== 4) {
                        throw new RuntimeException(
                            'MCQ question in section ' . ($index + 1) . ' must have exactly 4 options.'
                        );
                    }

                    $correctCount = collect($options)
                        ->where('is_correct', true)
                        ->count();

                    if ($correctCount !== 1) {
                        throw new RuntimeException(
                            'MCQ question in section ' . ($index + 1) . ' must have exactly one correct option.'
                        );
                    }
                }
            }
        }
    }

    private function validateQuestionsAgainstSyllabus(array $questions, ExamPortion $examPortion): void
    {
        $allowedLessonIds = $examPortion->lessons
            ->pluck('lesson_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        if (empty($allowedLessonIds)) {
            throw new RuntimeException('No valid syllabus lesson IDs found.');
        }

        foreach ($questions as $index => $question) {
            $lessonId = (int) ($question['lesson_id'] ?? 0);

            if (! $lessonId || ! in_array($lessonId, $allowedLessonIds, true)) {
                throw new RuntimeException(
                    'AI generated question #' . ($index + 1) . ' from outside approved syllabus.'
                );
            }
        }
    }

    private function normalizeOptions($options): ?array
    {
        if (! is_array($options)) {
            return null;
        }

        $normalized = collect($options)
            ->filter(fn ($option) => is_array($option) && ! empty($option['option_text']))
            ->map(fn ($option, $index) => [
                'option_text' => trim((string) $option['option_text']),
                'is_correct' => (bool) ($option['is_correct'] ?? false),
                'sort_order' => $index,
            ])
            ->values()
            ->all();

        return $normalized ?: null;
    }

    private function normalizeMatchPairs($pairs): ?array
    {
        if (! is_array($pairs)) {
            return null;
        }

        $normalized = collect($pairs)
            ->filter(fn ($pair) => is_array($pair) && ! empty($pair['left_value']) && ! empty($pair['right_value']))
            ->map(fn ($pair, $index) => [
                'left_value' => trim((string) $pair['left_value']),
                'right_value' => trim((string) $pair['right_value']),
                'sort_order' => $index,
            ])
            ->values()
            ->all();

        return $normalized ?: null;
    }
}