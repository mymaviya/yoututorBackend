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
            'prompt' => null,
            'ai_response' => null,
            'progress_percentage' => 0,
            'current_section' => null,
            'progress_message' => 'Preparing AI generation...',
            'started_at' => now(),
            'completed_at' => null,
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

            $questions = $this->generateSectionWise(
                $blueprint,
                $generation,
                $examPortion
            );

            if (empty($questions)) {
                throw new RuntimeException('AI did not return any valid questions.');
            }

            $this->validateQuestionsAgainstBlueprint(
                $questions,
                $blueprint,
                $examPortion
            );

            $this->validateQuestionsAgainstSyllabus(
                $questions,
                $examPortion
            );

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
                'progress_percentage' => 100,
                'current_section' => null,
                'progress_message' => 'AI paper generated successfully.',
                'completed_at' => now(),
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
                'progress_message' => 'Generation failed.',
            ]);

            throw $e;
        }
    }

    private function generateSectionWise(
        PaperBlueprint $blueprint,
        AiPaperGeneration $generation,
        ExamPortion $examPortion
    ): array {
        $allQuestions = [];

        $sections = $blueprint->sections->values();

        if ($sections->isEmpty()) {
            throw new RuntimeException('Blueprint has no sections configured.');
        }

        foreach ($sections as $sectionIndex => $section) {
            $totalSections = $sections->count();

            $sectionName = $section->section_name
                ?: 'Section ' . ($sectionIndex + 1);

            $generation->update([
                'current_section' => $sectionName,
                'progress_message' => 'Generating ' . $sectionName . '...',
                'progress_percentage' => (int) floor(($sectionIndex / $totalSections) * 100),
            ]);

            $prompt = $this->buildSectionPrompt(
                $blueprint,
                $generation,
                $examPortion,
                $section,
                $sectionIndex
            );

            $this->appendGenerationPrompt(
                $generation,
                'SECTION ' . ($sectionIndex + 1) . ' PROMPT',
                $prompt
            );

            $attempt = 1;
            $maxAttempts = 5;

            while ($attempt <= $maxAttempts) {
                $response = $this->callAi($prompt);

                $this->appendGenerationResponse(
                    $generation,
                    'SECTION ' . ($sectionIndex + 1) . ' RESPONSE - ATTEMPT ' . $attempt,
                    $response
                );

                $sectionQuestions = $this->normalizeQuestions($response);

                try {
                    $this->validateSectionQuestions(
                        $sectionQuestions,
                        $section,
                        $sectionIndex,
                        $examPortion
                    );

                    $allQuestions = array_merge($allQuestions, $sectionQuestions);

                    $generation->update([
                        'progress_message' => $sectionName . ' generated successfully.',
                        'progress_percentage' => (int) floor((($sectionIndex + 1) / $totalSections) * 100),
                    ]);

                    break;
                } catch (\Throwable $e) {
                    if ($attempt >= $maxAttempts) {
                        throw $e;
                    }

                    $attempt++;

                    $expectedCount = (int) ($section->question_count ?? 1);
                    $currentCount = count($sectionQuestions);
                    $missingCount = $expectedCount - $currentCount;

                    if ($missingCount > 0 && $currentCount > 0) {
                        $missingPrompt = $this->buildMissingQuestionsPrompt(
                            $blueprint,
                            $generation,
                            $examPortion,
                            $section,
                            $sectionIndex,
                            $missingCount,
                            $sectionQuestions,
                            $e->getMessage()
                        );

                        $missingResponse = $this->callAi($missingPrompt);

                        $this->appendGenerationResponse(
                            $generation,
                            'SECTION ' . ($sectionIndex + 1) . ' MISSING QUESTIONS RESPONSE',
                            $missingResponse
                        );

                        $missingQuestions = $this->normalizeQuestions($missingResponse);

                        $sectionQuestions = array_merge(
                            $sectionQuestions,
                            $missingQuestions
                        );

                        try {
                            $this->validateSectionQuestions(
                                $sectionQuestions,
                                $section,
                                $sectionIndex,
                                $examPortion
                            );

                            $allQuestions = array_merge(
                                $allQuestions,
                                $sectionQuestions
                            );

                            break;
                        } catch (\Throwable $missingError) {
                            $prompt = $this->buildSectionPrompt(
                                $blueprint,
                                $generation,
                                $examPortion,
                                $section,
                                $sectionIndex,
                                $missingError->getMessage()
                            );
                        }
                    } else {
                        $prompt = $this->buildSectionPrompt(
                            $blueprint,
                            $generation,
                            $examPortion,
                            $section,
                            $sectionIndex,
                            $e->getMessage()
                        );
                    }

                    $this->appendGenerationPrompt(
                        $generation,
                        'SECTION ' . ($sectionIndex + 1) . ' RETRY PROMPT - ATTEMPT ' . $attempt,
                        $prompt
                    );
                }
            }
        }

        return $allQuestions;
    }

    private function buildSectionPrompt(
        PaperBlueprint $blueprint,
        AiPaperGeneration $generation,
        ExamPortion $examPortion,
        $section,
        int $sectionIndex,
        ?string $previousError = null
    ): string {
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

        $questionCount = (int) ($section->question_count ?? 1);
        $marksEach = (float) ($section->marks_per_question ?? 1);

        $payload = [
            'instruction' => 'Generate questions for ONE blueprint section only. Return only valid JSON. Do not include markdown.',
            'previous_error' => $previousError,
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
            'section' => [
                'section_index' => $sectionIndex,
                'section_id' => $section->id,
                'section_name' => $section->section_name,
                'question_type_master_id' => $section->question_type_master_id,
                'question_type' => $section->questionType?->name ?? $section->questionType?->slug,
                'difficulty' => $section->difficulty ?? $generation->difficulty ?? 'medium',
                'question_count' => $questionCount,
                'marks_each' => $marksEach,
                'total_section_marks' => $questionCount * $marksEach,
                'instructions' => $section->instructions,
                'bloom_distribution' => $bloomDistribution,
            ],
            'exam_syllabus' => [
                'exam_portion_id' => $examPortion->id,
                'exam_name_id' => $examPortion->exam_name_id ?? $blueprint->exam_name_id ?? null,
                'status' => $examPortion->status,
                'lessons' => $this->buildSyllabusLessons($examPortion),
            ],
            'rules' => [
                'Generate ONLY this section.',
                'Return exactly ' . $questionCount . ' questions in the questions array.',
                'Do not return fewer than ' . $questionCount . ' questions.',
                'Do not return more than ' . $questionCount . ' questions.',
                'Every question must have section_index exactly ' . $sectionIndex . '.',
                'Every question must have question_type_master_id exactly ' . $section->question_type_master_id . '.',
                'Every question must have marks exactly ' . $marksEach . '.',
                'Every question must include one valid lesson_id from exam_syllabus.lessons.',
                'Generate questions only from the listed lesson names, topics, and learning objectives.',
                'Do not generate any out-of-syllabus question.',
                'Do not repeat questions.',
                'For MCQ or multiple choice questions, provide exactly 4 options and exactly one correct option.',
                'For match column questions, provide match_pairs.',
                'Do not use copyrighted textbook passages.',
                'Return only JSON with a questions array.',
            ],
        ];

        return json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
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

    private function buildSyllabusLessons(?ExamPortion $examPortion): array
    {
        if (! $examPortion) {
            return [];
        }

        return ($examPortion->lessons ?? collect())
            ->filter(fn($portionLesson) => $portionLesson->lesson)
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

        $payload = [
            'model' => $model,
            'temperature' => 0.1,
            'response_format' => [
                'type' => 'json_object',
            ],
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'You are an expert school assessment question paper creator. Return only valid JSON. Generate exactly the requested number of questions for the given section.',
                ],
                [
                    'role' => 'user',
                    'content' => $prompt,
                ],
            ],
        ];

        if (str_starts_with($model, 'gpt-4o')) {
            $payload['max_tokens'] = 12000;
        } else {
            $payload['max_completion_tokens'] = 12000;
        }

        $response = Http::withToken($apiKey)
            ->timeout(600)
            ->post('https://api.openai.com/v1/chat/completions', $payload);

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
            ->filter(fn($item) => is_array($item) && ! empty($item['question']))
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

    private function validateSectionQuestions(
        array $questions,
        $section,
        int $sectionIndex,
        ExamPortion $examPortion
    ): void {
        $expectedCount = (int) ($section->question_count ?? 1);
        $actualCount = count($questions);

        if ($actualCount !== $expectedCount) {
            throw new RuntimeException(
                'Section ' . ($sectionIndex + 1) . ' requires ' . $expectedCount . ' questions, but AI generated ' . $actualCount . '.'
            );
        }

        foreach ($questions as $index => $question) {
            if ((int) ($question['section_index'] ?? -1) !== $sectionIndex) {
                throw new RuntimeException(
                    'Question #' . ($index + 1) . ' has invalid section_index.'
                );
            }

            if (
                ! empty($section->question_type_master_id) &&
                (int) ($question['question_type_master_id'] ?? 0) !== (int) $section->question_type_master_id
            ) {
                throw new RuntimeException(
                    'Question #' . ($index + 1) . ' has invalid question_type_master_id.'
                );
            }

            $expectedMarks = (float) ($section->marks_per_question ?? 1);
            $actualMarks = (float) ($question['marks'] ?? 0);

            if ($actualMarks !== $expectedMarks) {
                throw new RuntimeException(
                    'Question #' . ($index + 1) . ' has invalid marks. Expected ' . $expectedMarks . ', got ' . $actualMarks . '.'
                );
            }

            $this->validateQuestionTypePayload($question, $section, $index);
        }

        $this->validateQuestionsAgainstSyllabus($questions, $examPortion);
    }

    private function validateQuestionsAgainstBlueprint(
        array $questions,
        PaperBlueprint $blueprint,
        ExamPortion $examPortion
    ): void {
        $sections = $blueprint->sections->values();

        $expectedTotal = $sections->sum(fn($section) => (int) $section->question_count);
        $actualTotal = count($questions);

        if ($actualTotal !== $expectedTotal) {
            throw new RuntimeException(
                "Blueprint requires {$expectedTotal} total questions, but AI generated {$actualTotal}."
            );
        }

        foreach ($sections as $index => $section) {
            $sectionQuestions = collect($questions)
                ->where('section_index', $index)
                ->values()
                ->all();

            $this->validateSectionQuestions(
                $sectionQuestions,
                $section,
                $index,
                $examPortion
            );
        }
    }

    private function validateQuestionsAgainstSyllabus(array $questions, ExamPortion $examPortion): void
    {
        $allowedLessonIds = $examPortion->lessons
            ->pluck('lesson_id')
            ->filter()
            ->map(fn($id) => (int) $id)
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

    private function validateQuestionTypePayload(array $question, $section, int $index): void
    {
        $typeName = strtolower((string) ($section->questionType?->name ?? $section->questionType?->slug ?? ''));

        if (
            str_contains($typeName, 'mcq') ||
            str_contains($typeName, 'multiple')
        ) {
            $options = $question['options'] ?? [];

            if (! is_array($options) || count($options) !== 4) {
                throw new RuntimeException(
                    'MCQ question #' . ($index + 1) . ' must have exactly 4 options.'
                );
            }

            $correctCount = collect($options)
                ->where('is_correct', true)
                ->count();

            if ($correctCount !== 1) {
                throw new RuntimeException(
                    'MCQ question #' . ($index + 1) . ' must have exactly one correct option.'
                );
            }
        }
    }

    private function appendGenerationPrompt(
        AiPaperGeneration $generation,
        string $title,
        string $prompt
    ): void {
        $generation->update([
            'prompt' => trim(
                ($generation->prompt ?? '')
                    . "\n\n==================== {$title} ====================\n"
                    . $prompt
            ),
        ]);
    }

    private function appendGenerationResponse(
        AiPaperGeneration $generation,
        string $title,
        array $response
    ): void {
        $generation->update([
            'ai_response' => trim(
                ($generation->ai_response ?? '')
                    . "\n\n==================== {$title} ====================\n"
                    . json_encode(
                        $response,
                        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
                    )
            ),
        ]);
    }

    private function normalizeOptions($options): ?array
    {
        if (! is_array($options)) {
            return null;
        }

        $normalized = collect($options)
            ->filter(fn($option) => is_array($option) && ! empty($option['option_text']))
            ->map(fn($option, $index) => [
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
            ->filter(fn($pair) => is_array($pair) && ! empty($pair['left_value']) && ! empty($pair['right_value']))
            ->map(fn($pair, $index) => [
                'left_value' => trim((string) $pair['left_value']),
                'right_value' => trim((string) $pair['right_value']),
                'sort_order' => $index,
            ])
            ->values()
            ->all();

        return $normalized ?: null;
    }

    private function buildMissingQuestionsPrompt(
        PaperBlueprint $blueprint,
        AiPaperGeneration $generation,
        ExamPortion $examPortion,
        $section,
        int $sectionIndex,
        int $missingCount,
        array $existingQuestions,
        ?string $previousError = null
    ): string {
        $existingQuestionTexts = collect($existingQuestions)
            ->pluck('question')
            ->values()
            ->all();

        $marksEach = (float) ($section->marks_per_question ?? 1);

        $payload = [
            'instruction' => 'Generate only the missing questions for this section. Return only valid JSON. Do not include markdown.',
            'previous_error' => $previousError,
            'missing_question_count' => $missingCount,
            'existing_questions_do_not_repeat' => $existingQuestionTexts,
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
            ],
            'section' => [
                'section_index' => $sectionIndex,
                'section_id' => $section->id,
                'section_name' => $section->section_name,
                'question_type_master_id' => $section->question_type_master_id,
                'question_type' => $section->questionType?->name ?? $section->questionType?->slug,
                'difficulty' => $section->difficulty ?? $generation->difficulty ?? 'medium',
                'missing_question_count' => $missingCount,
                'marks_each' => $marksEach,
                'instructions' => $section->instructions,
            ],
            'exam_syllabus' => [
                'exam_portion_id' => $examPortion->id,
                'exam_name_id' => $examPortion->exam_name_id ?? $blueprint->exam_name_id ?? null,
                'status' => $examPortion->status,
                'lessons' => $this->buildSyllabusLessons($examPortion),
            ],
            'rules' => [
                'Generate exactly ' . $missingCount . ' missing questions.',
                'Do not regenerate existing questions.',
                'Do not repeat any question from existing_questions_do_not_repeat.',
                'Every question must have section_index exactly ' . $sectionIndex . '.',
                'Every question must have question_type_master_id exactly ' . $section->question_type_master_id . '.',
                'Every question must have marks exactly ' . $marksEach . '.',
                'Every question must include one valid lesson_id from exam_syllabus.lessons.',
                'For MCQ or multiple choice questions, provide exactly 4 options and exactly one correct option.',
                'Return only JSON with a questions array.',
            ],
        ];

        return json_encode(
            $payload,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
        );
    }
}
