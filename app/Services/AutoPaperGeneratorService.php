<?php

namespace App\Services;

use App\Models\PaperBlueprint;
use App\Models\Question;
use Illuminate\Support\Collection;

class AutoPaperGeneratorService
{
    protected array $usedQuestionIds = [];
    protected array $shortages = [];

    public function generate(PaperBlueprint $blueprint): array
    {
        $this->usedQuestionIds = [];
        $this->shortages = [];

        $paperSections = [];

        $blueprint->load([
            'grade',
            'subject',
            'examName',
            'sections',
            'bloomLevels',
        ]);

        $sections = $this->formatSections($blueprint);

        foreach ($sections as $section) {
            $sectionQuestions = [];

            foreach ($section['items'] as $item) {
                $questions = $this->pickQuestions(
                    blueprint: $blueprint,
                    item: $item
                );

                $sectionQuestions[] = [
                    'question_type' => $item['question_type'],
                    'difficulty' => $item['difficulty'] ?? null,
                    'bloom_level' => $item['bloom_level'] ?? null,
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

        return [
            'blueprint' => $blueprint,
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

    protected function pickQuestions(PaperBlueprint $blueprint, array $item): Collection
    {
        $requiredCount = (int) $item['question_count'];

        $query = Question::with([
            'grade',
            'subject',
            'lesson',
            'options',
            'matchPairs',
        ])
            ->where('status', 'approved')
            ->where('grade_id', $blueprint->grade_id)
            ->where('subject_id', $blueprint->subject_id)
            ->where('type', $item['question_type'])
            ->whereNotIn('id', $this->usedQuestionIds);

        if (!empty($item['difficulty'])) {
            $query->where('difficulty', $item['difficulty']);
        }

        if (!empty($item['bloom_level'])) {
            $query->where('bloom_level', $item['bloom_level']);
        }

        $questions = $query
            ->inRandomOrder()
            ->limit($requiredCount)
            ->get();

        if ($questions->count() < $requiredCount) {
            $this->shortages[] = [
                'question_type' => $item['question_type'],
                'difficulty' => $item['difficulty'] ?? null,
                'bloom_level' => $item['bloom_level'] ?? null,
                'required' => $requiredCount,
                'available' => $questions->count(),
                'missing' => $requiredCount - $questions->count(),
            ];
        }

        $this->usedQuestionIds = array_merge(
            $this->usedQuestionIds,
            $questions->pluck('id')->toArray()
        );

        return $questions;
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
                            'question_type' => $row->question_type,
                            'difficulty' => $row->difficulty,
                            'bloom_level' => $row->bloom_level,
                            'question_count' => $row->question_count,
                            'marks_per_question' => $row->marks_per_question,
                        ];
                    })->toArray(),
                ];
            })
            ->toArray();
    }
}
