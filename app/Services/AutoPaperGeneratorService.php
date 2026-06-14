<?php

namespace App\Services;

use App\Models\PaperBlueprint;
use App\Models\Question;
use Illuminate\Support\Collection;

class AutoPaperGeneratorService
{
    protected array $usedQuestionIds = [];
    protected array $shortages = [];

    public function generate(PaperBlueprint $blueprint, bool $moderateMode = false): array
    {
        $this->usedQuestionIds = [];
        $this->shortages = [];

        $blueprint->loadMissing([
            'grade',
            'subject',
            'examName',
            'sections.questionType',
            'sections.bloomLevels',
        ]);

        $paperSections = [];
        $globalBloomLevels = $this->getGlobalBloomLevels($blueprint);
        $remainingBloomCounts = $globalBloomLevels
            ->mapWithKeys(fn ($bloom) => [
                $bloom['bloom_level'] => (int) $bloom['calculated_count'],
            ])
            ->toArray();

        $sections = $this->formatSections($blueprint);

        foreach ($sections as $section) {
            $sectionQuestions = [];

            foreach ($section['items'] as $item) {
                $questions = $this->pickQuestions(
                    blueprint: $blueprint,
                    item: $item,
                    remainingBloomCounts: $remainingBloomCounts,
                    moderateMode: $moderateMode
                );

                foreach ($questions as $question) {
                    if ($question->bloom_level && isset($remainingBloomCounts[$question->bloom_level])) {
                        $remainingBloomCounts[$question->bloom_level]--;
                    }
                }

                $sectionQuestions[] = [
                    'question_type' => $item['question_type'],
                    'question_type_name' => $item['question_type_name'],
                    'question_type_master_id' => $item['question_type_master_id'],
                    'difficulty' => $item['difficulty'] ?? null,
                    'required_count' => (int) $item['question_count'],
                    'marks_per_question' => (float) $item['marks_per_question'],
                    'questions' => $questions,
                ];
            }

            $paperSections[] = [
                'section_name' => $section['section_name'],
                'instructions' => $section['instructions'] ?? null,
                'items' => $sectionQuestions,
            ];
        }

        $this->validateBloomCompletion($remainingBloomCounts);

        return [
            'blueprint' => $blueprint,
            'bloom_levels' => $globalBloomLevels,
            'sections' => $paperSections,
            'shortages' => $this->shortages,
            'total_questions' => collect($paperSections)
                ->flatMap(fn ($section) => collect($section['items'])
                    ->flatMap(fn ($item) => $item['questions']))
                ->count(),
            'total_marks' => collect($paperSections)
                ->flatMap(fn ($section) => $section['items'])
                ->sum(function ($item) {
                    return count($item['questions']) * $item['marks_per_question'];
                }),
        ];
    }

    protected function pickQuestions(
        PaperBlueprint $blueprint,
        array $item,
        array $remainingBloomCounts,
        bool $moderateMode
    ): Collection {
        $requiredCount = (int) $item['question_count'];
        $picked = collect();

        $activeBloomLevels = collect($remainingBloomCounts)
            ->filter(fn ($count) => (int) $count > 0)
            ->keys()
            ->values();

        foreach ($activeBloomLevels as $bloomLevel) {
            if ($picked->count() >= $requiredCount) {
                break;
            }

            $neededForItem = $requiredCount - $picked->count();
            $neededForBloom = (int) ($remainingBloomCounts[$bloomLevel] ?? 0);
            $limit = min($neededForItem, $neededForBloom);

            if ($limit <= 0) {
                continue;
            }

            $questions = $this->baseQuestionQuery($blueprint, $item, $moderateMode)
                ->where('bloom_level', $bloomLevel)
                ->whereNotIn('id', array_merge($this->usedQuestionIds, $picked->pluck('id')->toArray()))
                ->inRandomOrder()
                ->limit($limit)
                ->get();

            $picked = $picked->merge($questions);
        }

        if ($picked->count() < $requiredCount) {
            $fallback = $this->baseQuestionQuery($blueprint, $item, $moderateMode)
                ->whereNotIn('id', array_merge($this->usedQuestionIds, $picked->pluck('id')->toArray()))
                ->inRandomOrder()
                ->limit($requiredCount - $picked->count())
                ->get();

            $picked = $picked->merge($fallback);
        }

        if ($picked->count() < $requiredCount) {
            $this->shortages[] = [
                'question_type' => $item['question_type'],
                'question_type_name' => $item['question_type_name'],
                'difficulty' => $moderateMode ? 'Bypassed' : ($item['difficulty'] ?? null),
                'required' => $requiredCount,
                'available' => $picked->count(),
                'missing' => $requiredCount - $picked->count(),
            ];
        }

        $this->usedQuestionIds = array_merge(
            $this->usedQuestionIds,
            $picked->pluck('id')->toArray()
        );

        return $picked->values();
    }

    protected function baseQuestionQuery(PaperBlueprint $blueprint, array $item, bool $moderateMode)
    {
        return Question::with([
            'grade',
            'subject',
            'lesson',
            'type',
            'options',
            'matchPairs',
        ])
            ->where('status', 'approved')
            ->where('grade_id', $blueprint->grade_id)
            ->where('subject_id', $blueprint->subject_id)
            ->where('question_type_master_id', $item['question_type_master_id'])
            ->when($blueprint->stream_id, fn ($q) => $q->where('stream_id', $blueprint->stream_id))
            ->when(!$moderateMode && !empty($item['difficulty']), fn ($q) => $q->where('difficulty', $item['difficulty']));
    }

    protected function validateBloomCompletion(array $remainingBloomCounts): void
    {
        foreach ($remainingBloomCounts as $bloomLevel => $remaining) {
            if ((int) $remaining > 0) {
                $this->shortages[] = [
                    'type' => 'bloom',
                    'bloom_level' => $bloomLevel,
                    'required_missing' => (int) $remaining,
                    'message' => ucfirst($bloomLevel) . " still requires {$remaining} question(s).",
                ];
            }
        }
    }

    protected function getGlobalBloomLevels(PaperBlueprint $blueprint): Collection
    {
        foreach ($blueprint->sections as $section) {
            if ($section->bloomLevels && $section->bloomLevels->isNotEmpty()) {
                return $section->bloomLevels
                    ->filter(fn ($bloom) => (int) $bloom->calculated_count > 0)
                    ->map(fn ($bloom) => [
                        'bloom_level' => $bloom->bloom_level,
                        'percentage' => (float) $bloom->percentage,
                        'calculated_count' => (int) $bloom->calculated_count,
                    ])
                    ->values();
            }
        }

        return collect();
    }

    protected function formatSections(PaperBlueprint $blueprint): array
    {
        return $blueprint->sections
            ->groupBy(function ($row) {
                $sortOrder = (int) $row->sort_order;

                return $sortOrder >= 100
                    ? intdiv($sortOrder, 100)
                    : $sortOrder;
            })
            ->values()
            ->map(function ($rows) {
                $first = $rows->first();

                return [
                    'section_name' => $first->section_name,
                    'instructions' => $first->instructions,
                    'items' => $rows->values()->map(function ($row) {
                        return [
                            'question_type_master_id' => $row->question_type_master_id,
                            'question_type' => $row->questionType?->slug,
                            'question_type_name' => $row->questionType?->name,
                            'difficulty' => $row->difficulty,
                            'question_count' => (int) $row->question_count,
                            'marks_per_question' => (float) $row->marks_per_question,
                        ];
                    })->toArray(),
                ];
            })
            ->toArray();
    }
}