<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\PaperBlueprint;
use App\Models\QuestionPaper;
use App\Services\AutoPaperGeneratorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Services\AuditService;

class PaperGeneratorController extends Controller
{
    public function preview(
        Request $request,
        AutoPaperGeneratorService $generator
    ) {
        $data = $request->validate([
            'paper_blueprint_id' => 'required|exists:paper_blueprints,id',
        ]);

        $blueprint = PaperBlueprint::with([
            'grade',
            'subject',
            'examName',
            'sections',
            'bloomLevels',
        ])->findOrFail($data['paper_blueprint_id']);

        $result = $generator->generate($blueprint);

        return response()->json([
            'message' => 'Paper preview generated successfully',
            'data' => $result,
        ]);
    }

    public function generate(
        Request $request,
        AutoPaperGeneratorService $generator
    ) {
        $data = $request->validate([
            'paper_blueprint_id' => 'required|exists:paper_blueprints,id',
            'title' => 'nullable|string|max:255',
            'exam_name' => 'nullable|string|max:255',
            'duration' => 'nullable|string|max:255',
            'instructions' => 'nullable|string',
        ]);

        $blueprint = PaperBlueprint::with([
            'grade',
            'subject',
            'examName',
            'sections',
            'bloomLevels',
        ])->findOrFail($data['paper_blueprint_id']);

        $result = $generator->generate($blueprint);

        if (!empty($result['shortages'])) {
            return response()->json([
                'message' => 'Not enough approved questions available for this blueprint.',
                'shortages' => $result['shortages'],
                'preview' => $result,
            ], 422);
        }

        return DB::transaction(function () use ($data, $blueprint, $result) {
            $paper = QuestionPaper::create([
                'grade_id' => $blueprint->grade_id,
                'subject_id' => $blueprint->subject_id,
                'title' => $data['title'] ?? $blueprint->title,
                'exam_name' => $data['exam_name'] ?? $blueprint->examName?->name,
                'duration' => $data['duration'] ?? null,
                'instructions' => $data['instructions'] ?? null,
                'total_marks' => $result['total_marks'],
                'created_by' => auth()->id(),
            ]);

            $sortOrder = 1;

            foreach ($result['sections'] as $section) {
                foreach ($section['items'] as $item) {
                    foreach ($item['questions'] as $question) {
                        $paper->questions()->create([
                            'question_id' => $question->id,
                            'marks' => $item['marks_per_question'],
                            'section_name' => $section['section_name'],
                            'sort_order' => $sortOrder++,
                        ]);
                    }
                }
            }

            AuditService::log(
                'Paper Generation',
                'Generated question paper from blueprint',
                "Generated paper '{$paper->title}' (ID: {$paper->id}) from blueprint '{$blueprint->title}' (ID: {$blueprint->id})",
                null,
                $paper->toArray(),
                auth()->id()

            );

            return response()->json([
                'message' => 'Question paper generated successfully',
                'data' => $paper->load([
                    'grade',
                    'subject',
                    'questions.question.options',
                    'questions.question.matchPairs',
                ]),
            ], 201);
        });
    }
}
