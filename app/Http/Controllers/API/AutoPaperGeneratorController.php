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

        $blueprint = PaperBlueprint::with(['sections.questionType'])->findOrFail($data['blueprint_id']);

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

            $questions = Question::with(['options', 'matchPairs', 'type'])
                ->where('status', 'approved')
                ->where('grade_id', $blueprint->grade_id)
                ->where('subject_id', $blueprint->subject_id)
                ->where('question_type_master_id', $section->question_type_master_id)
                ->when($blueprint->stream_id, fn ($q) => $q->where('stream_id', $blueprint->stream_id))
                ->when($data['lesson_id'] ?? null, fn ($q) => $q->where('lesson_id', $data['lesson_id']))
                ->when($section->difficulty, fn ($q) => $q->where('difficulty', $section->difficulty))
                ->whereNotIn('id', $usedQuestionIds)
                ->inRandomOrder()
                ->take($section->question_count)
                ->get();

            $usedQuestionIds = array_merge($usedQuestionIds, $questions->pluck('id')->toArray());

            $questions = $questions->map(function ($question) use ($section) {
                $question->marks = $section->marks_per_question;
                $question->type_slug = $question->type?->slug;
                return $question;
            });

            $sections[$sectionName]['groups'][] = [
                'question_type_master_id' => $section->question_type_master_id,
                'type' => $section->questionType?->slug,
                'question_type' => $section->questionType?->slug,
                'question_type_name' => $section->questionType?->name,
                'difficulty' => $section->difficulty,
                'marks_per_question' => $section->marks_per_question,
                'questions' => $questions,
            ];

            $sections[$sectionName]['questions'] = array_merge($sections[$sectionName]['questions'], $questions->toArray());
        }

        return response()->json(['sections' => array_values($sections)]);
    }
}
