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

        $blueprint = PaperBlueprint::with('sections')
            ->findOrFail($data['blueprint_id']);

        $usedQuestionIds = [];
        $sections = [];

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
                ->get()
                ->map(function ($question) use ($section) {
                    $question->marks = $section->marks_per_question;
                    return $question;
                });

            $usedQuestionIds = array_merge(
                $usedQuestionIds,
                $questions->pluck('id')->toArray()
            );

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
