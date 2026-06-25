<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PaperBlueprint;
use App\Models\QuestionPaper;
use App\Services\AuditService;
use App\Services\AutoPaperGeneratorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PaperGeneratorController extends Controller
{
    public function preview(Request $request, AutoPaperGeneratorService $generator)
    {
        $data = $request->validate([
            'paper_blueprint_id' => 'required|exists:paper_blueprints,id',
            'moderate_mode' => 'nullable|boolean',
        ]);

        // PaperBlueprint model must use BelongsToSubscription.
        // Global scope blocks access to another school's blueprint.
        $blueprint = PaperBlueprint::with([
            'grade',
            'subject',
            'examName',
            'sections.questionType',
            'sections.bloomLevels',
        ])->findOrFail($data['paper_blueprint_id']);

        $result = $generator->generate(
            $blueprint,
            $request->boolean('moderate_mode')
        );

        return response()->json([
            'message' => 'Paper preview generated successfully',
            'data' => $result,
        ]);
    }

    public function generate(Request $request, AutoPaperGeneratorService $generator)
    {
        $data = $request->validate([
            'paper_blueprint_id' => 'required|exists:paper_blueprints,id',
            'title' => 'nullable|string|max:255',
            'exam_name_id' => 'nullable|exists:exam_names,id',
            'exam_name' => 'nullable|string|max:255',
            'duration' => 'nullable|string|max:255',
            'duration_minutes' => 'nullable|integer',
            'instructions' => 'nullable|string',
            'moderate_mode' => 'nullable|boolean',
        ]);

        // PaperBlueprint model must use BelongsToSubscription.
        $blueprint = PaperBlueprint::with([
            'grade',
            'subject',
            'examName',
            'sections.questionType',
            'sections.bloomLevels',
        ])->findOrFail($data['paper_blueprint_id']);

        $result = $generator->generate(
            $blueprint,
            $request->boolean('moderate_mode')
        );

        if (! empty($result['shortages'])) {
            return response()->json([
                'message' => 'Not enough approved questions available for this blueprint.',
                'shortages' => $result['shortages'],
                'preview' => $result,
            ], 422);
        }

        return DB::transaction(function () use ($data, $blueprint, $result) {
            $paper = QuestionPaper::create([
                // QuestionPaper model should use BelongsToSubscription.
                // This explicit value is kept for clarity and safety.
                'subscription_id' => auth()->user()?->subscription_id,
                'grade_id' => $blueprint->grade_id,
                'stream_id' => $blueprint->stream_id,
                'subject_id' => $blueprint->subject_id,
                'exam_name_id' => $data['exam_name_id'] ?? $blueprint->exam_name_id ?? null,
                'paper_blueprint_id' => $blueprint->id,
                'title' => $data['title'] ?? $blueprint->name,
                'exam_type' => $data['exam_name']
                    ?? $blueprint->examName?->name
                    ?? 'Generated Paper',
                'duration_minutes' => $data['duration_minutes']
                    ?? $blueprint->duration_minutes
                    ?? null,
                'instructions' => $data['instructions'] ?? null,
                'total_marks' => $result['total_marks'],
                'status' => 'draft',
                'created_by' => auth()->id(),
            ]);

            $sortOrder = 1;

            foreach ($result['sections'] as $section) {
                foreach ($section['items'] as $item) {
                    foreach ($item['questions'] as $question) {
                        $paper->questions()->create([
                            'question_id' => $question->id,
                            'marks' => $item['marks_per_question'],
                            'section' => $section['section_name'],
                            'sort_order' => $sortOrder++,
                        ]);
                    }
                }
            }

            AuditService::log(
                'Paper Generation',
                'Generated question paper from blueprint',
                "Generated paper '{$paper->title}' (ID: {$paper->id}) from blueprint '{$blueprint->name}' (ID: {$blueprint->id})",
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
