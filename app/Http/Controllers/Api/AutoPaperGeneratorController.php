<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PaperBlueprint;
use App\Models\Question;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AutoPaperGeneratorController extends Controller
{
    public function generateFromBlueprint(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'paper_blueprint_id' => [
                'required',
                Rule::exists('paper_blueprints', 'id')
                    ->when(
                        ! $this->isSuperAdmin($user),
                        fn ($rule) => $rule->where('subscription_id', $user?->subscription_id)
                    ),
            ],
            'moderate_mode' => 'nullable|boolean',
        ]);

        $moderateMode = $request->boolean('moderate_mode');

        // PaperBlueprint model should use BelongsToSubscription.
        // The validation above also prevents selecting another school's blueprint ID.
        $blueprint = PaperBlueprint::with([
            'sections.questionType',
            'sections.bloomLevels',
        ])->findOrFail($request->paper_blueprint_id);

        $selectedQuestions = collect();
        $selectedQuestionIds = collect();
        $errors = [];

        foreach ($blueprint->sections as $section) {
            $bloomRules = $section->bloomLevels;

            if ($bloomRules->isNotEmpty()) {
                foreach ($bloomRules as $bloomRule) {
                    $requiredCount = (int) $bloomRule->calculated_count;

                    if ($requiredCount <= 0) {
                        continue;
                    }

                    $questions = $this->baseQuestionQuery($blueprint, $section, $moderateMode)
                        ->where('bloom_level', $bloomRule->bloom_level)
                        ->when(
                            $selectedQuestionIds->isNotEmpty(),
                            fn ($query) => $query->whereNotIn('id', $selectedQuestionIds->all())
                        )
                        ->inRandomOrder()
                        ->limit($requiredCount)
                        ->get();

                    if ($questions->count() < $requiredCount) {
                        $errors[] = "{$section->section_name} - {$section->questionType?->name} - "
                            . ucfirst((string) $bloomRule->bloom_level)
                            . ": required {$requiredCount}, available {$questions->count()}";
                    }

                    $selectedQuestionIds = $selectedQuestionIds
                        ->merge($questions->pluck('id'))
                        ->unique()
                        ->values();

                    $selectedQuestions = $selectedQuestions->merge(
                        $questions->map(fn (Question $question) => $this->formatQuestion($question, $section))
                    );
                }

                continue;
            }

            $requiredCount = (int) $section->question_count;

            if ($requiredCount <= 0) {
                continue;
            }

            $questions = $this->baseQuestionQuery($blueprint, $section, $moderateMode)
                ->when(
                    $selectedQuestionIds->isNotEmpty(),
                    fn ($query) => $query->whereNotIn('id', $selectedQuestionIds->all())
                )
                ->inRandomOrder()
                ->limit($requiredCount)
                ->get();

            if ($questions->count() < $requiredCount) {
                $errors[] = "{$section->section_name} - {$section->questionType?->name}: required {$requiredCount}, available {$questions->count()}";
            }

            $selectedQuestionIds = $selectedQuestionIds
                ->merge($questions->pluck('id'))
                ->unique()
                ->values();

            $selectedQuestions = $selectedQuestions->merge(
                $questions->map(fn (Question $question) => $this->formatQuestion($question, $section))
            );
        }

        if (! empty($errors)) {
            return response()->json([
                'message' => 'Auto generation failed.',
                'errors' => [
                    'blueprint' => $errors,
                ],
            ], 422);
        }

        return response()->json([
            'message' => 'Paper generated successfully.',
            'data' => $selectedQuestions->values(),
        ]);
    }

    private function baseQuestionQuery(PaperBlueprint $blueprint, $section, bool $moderateMode)
    {
        // Question model should use BelongsToSubscription.
        // Global scope automatically applies: questions.subscription_id = auth user subscription_id.
        return Question::with([
                'type',
                'options',
                'matchPairs',
                'lesson',
            ])
            ->where('status', 'approved')
            ->where('grade_id', $blueprint->grade_id)
            ->where('subject_id', $blueprint->subject_id)
            ->where('question_type_master_id', $section->question_type_master_id)
            ->when($blueprint->stream_id, fn ($query) => $query->where('stream_id', $blueprint->stream_id))
            ->when(! $moderateMode && $section->difficulty, fn ($query) => $query->where('difficulty', $section->difficulty));
    }

    private function formatQuestion(Question $question, $section): array
    {
        return [
            'id' => $question->id,
            'question_id' => $question->id,
            'question' => $question->question,
            'type' => $question->type?->slug,
            'question_type' => $question->type?->slug,
            'question_type_master_id' => $question->question_type_master_id,
            'difficulty' => $question->difficulty,
            'bloom_level' => $question->bloom_level,
            'marks' => (float) $section->marks_per_question,
            'paper_section' => $section->section_name,
            'section' => $section->section_name,
            'options' => $question->options,
            'match_pairs' => $question->matchPairs,
            'lesson' => $question->lesson,
        ];
    }

    private function isSuperAdmin($user): bool
    {
        if (! $user) {
            return false;
        }

        if (method_exists($user, 'isSuperAdmin')) {
            return (bool) $user->isSuperAdmin();
        }

        return ($user->role ?? null) === 'super_admin'
            || ($user->role ?? null) === 'superadmin';
    }
}
