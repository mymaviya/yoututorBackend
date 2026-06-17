<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PaperBlueprint;
use App\Models\Question;
use Illuminate\Http\Request;

class AutoPaperGeneratorController extends Controller
{
    private function normalize($value): string
    {
        return strtolower(trim((string) $value));
    }

    public function generateFromBlueprint(Request $request)
    {
        $request->validate([
            'paper_blueprint_id' => 'required|exists:paper_blueprints,id',
            'moderate_mode' => 'nullable|boolean',
        ]);

        $moderateMode = $request->boolean('moderate_mode');

        $blueprint = PaperBlueprint::with([
            'sections.questionType',
            'sections.bloomLevels',
        ])->findOrFail($request->paper_blueprint_id);

        $selectedQuestions = collect();
        $errors = [];

        foreach ($blueprint->sections as $section) {
            $bloomRules = $section->bloomLevels;

            if ($bloomRules->isNotEmpty()) {
                foreach ($bloomRules as $bloomRule) {
                    $query = $this->baseQuestionQuery($blueprint, $section, $moderateMode)
                        ->where('bloom_level', $bloomRule->bloom_level)
                        ->whereNotIn('id', $selectedQuestions->pluck('id'));

                    $questions = $query
                        ->inRandomOrder()
                        ->limit((int) $bloomRule->calculated_count)
                        ->get();

                    if ($questions->count() < (int) $bloomRule->calculated_count) {
                        $errors[] = "{$section->section_name} - {$section->questionType?->name} - "
                            . ucfirst($bloomRule->bloom_level)
                            . ": required {$bloomRule->calculated_count}, available {$questions->count()}";
                    }

                    $selectedQuestions = $selectedQuestions->merge(
                        $questions->map(fn ($question) => $this->formatQuestion($question, $section))
                    );
                }

                continue;
            }

            $query = $this->baseQuestionQuery($blueprint, $section, $moderateMode)
                ->whereNotIn('id', $selectedQuestions->pluck('id'));

            $questions = $query
                ->inRandomOrder()
                ->limit((int) $section->question_count)
                ->get();

            if ($questions->count() < (int) $section->question_count) {
                $errors[] = "{$section->section_name} - {$section->questionType?->name}: required {$section->question_count}, available {$questions->count()}";
            }

            $selectedQuestions = $selectedQuestions->merge(
                $questions->map(fn ($question) => $this->formatQuestion($question, $section))
            );
        }

        if (!empty($errors)) {
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
            ->when($blueprint->stream_id, fn ($q) => $q->where('stream_id', $blueprint->stream_id))
            ->when(!$moderateMode && $section->difficulty, fn ($q) => $q->where('difficulty', $section->difficulty));
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
}