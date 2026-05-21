<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\PaperBlueprint;
use App\Models\Question;
use Illuminate\Http\Request;

class AutoPaperGeneratorController extends Controller
{
    public function generate(Request $request)
    {
        $data = $request->validate([
            'blueprint_id' => 'required|exists:paper_blueprints,id',
            'lesson_id' => 'nullable|exists:lessons,id',
        ]);

        $blueprint = PaperBlueprint::with(['sections', 'bloomLevels'])
            ->findOrFail($data['blueprint_id']);

        $usedQuestionIds = [];
        $sections = [];
        $hasBloomDistribution = $blueprint->bloomLevels->count() > 0;

        foreach ($blueprint->sections as $section) {

            $sectionName = $section->section_name;

            if (!isset($sections[$sectionName])) {
                $sections[$sectionName] = [
                    'name' => $sectionName,
                    'instructions' => $section->instructions,
                    'groups' => [],
                    'questions' => [],
                ];
            }

            if ($hasBloomDistribution) {
                $questions = collect();

                foreach ($blueprint->bloomLevels as $bloom) {
                    $count = (int) round(
                        ($section->question_count * $bloom->percentage) / 100
                    );

                    if ($count <= 0) {
                        continue;
                    }

                    $picked = Question::with(['options', 'matchPairs'])
                        ->where('status', 'approved')
                        ->where('grade_id', $blueprint->grade_id)
                        ->where('subject_id', $blueprint->subject_id)
                        ->where('type', $section->question_type)
                        ->where('bloom_level', $bloom->bloom_level)
                        ->when(!empty($section->difficulty), function ($q) use ($section) {
                            $q->where('difficulty', $section->difficulty);
                        })
                        ->whereNotIn('id', $usedQuestionIds)
                        ->inRandomOrder()
                        ->take($count)
                        ->get();

                    $usedQuestionIds = array_merge(
                        $usedQuestionIds,
                        $picked->pluck('id')->toArray()
                    );

                    $questions = $questions->merge($picked);
                }

                // Fill remaining if rounding gives less than required
                $remaining = $section->question_count - $questions->count();

                if ($remaining > 0) {
                    $extra = Question::with(['options', 'matchPairs'])
                        ->where('status', 'approved')
                        ->where('grade_id', $blueprint->grade_id)
                        ->where('subject_id', $blueprint->subject_id)
                        ->where('type', $section->question_type)
                        ->when(!empty($section->difficulty), function ($q) use ($section) {
                            $q->where('difficulty', $section->difficulty);
                        })
                        ->whereNotIn('id', $usedQuestionIds)
                        ->inRandomOrder()
                        ->take($remaining)
                        ->get();

                    $usedQuestionIds = array_merge(
                        $usedQuestionIds,
                        $extra->pluck('id')->toArray()
                    );

                    $questions = $questions->merge($extra);
                }
            } else {
                $questions = Question::with(['options', 'matchPairs'])
                    ->where('status', 'approved')
                    ->where('grade_id', $blueprint->grade_id)
                    ->where('subject_id', $blueprint->subject_id)
                    ->where('type', $section->question_type)
                    ->when(!empty($section->difficulty), function ($q) use ($section) {
                        $q->where('difficulty', $section->difficulty);
                    })
                    ->when(!empty($section->bloom_level), function ($q) use ($section) {
                        $q->where('bloom_level', $section->bloom_level);
                    })
                    ->whereNotIn('id', $usedQuestionIds)
                    ->inRandomOrder()
                    ->take($section->question_count)
                    ->get();

                $usedQuestionIds = array_merge(
                    $usedQuestionIds,
                    $questions->pluck('id')->toArray()
                );
            }

            // add marks here
            $questions = $questions->map(function ($question) use ($section) {
                $question->marks = $section->marks_per_question;
                return $question;
            });

            // then add to sections array
            $sections[$sectionName]['groups'][] = [
                'type' => $section->question_type,
                'difficulty' => $section->difficulty,
                'bloom_level' => $section->bloom_level,
                'marks_per_question' => $section->marks_per_question,
                'questions' => $questions,
            ];

            $sections[$sectionName]['questions'] = array_merge(
                $sections[$sectionName]['questions'],
                $questions->toArray()
            );
        }

        return response()->json([
            'sections' => array_values($sections)
        ]);
    }
}
