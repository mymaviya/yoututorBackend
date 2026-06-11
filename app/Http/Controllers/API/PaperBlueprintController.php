<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\PaperBlueprint;
use App\Models\Question;
use App\Models\QuestionTypeMaster;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PaperBlueprintController extends Controller
{
    private function relationships(): array
    {
        return ['grade', 'stream', 'subject', 'examName', 'sections.questionType'];
    }

    private function resolveQuestionTypeId($value): ?int
    {
        if (is_numeric($value)) return (int) $value;
        return QuestionTypeMaster::where('slug', $value)->orWhere('name', $value)->value('id');
    }

    private function formatBlueprint(PaperBlueprint $blueprint)
    {
        $blueprint->setRelation(
            'sections',
            $blueprint->sections
                ->groupBy('section_name')
                ->values()
                ->map(function ($rows) {
                    $first = $rows->first();
                    return [
                        'id' => $first->id,
                        'section_name' => $first->section_name,
                        'instructions' => $first->instructions,
                        'items' => $rows->values()->map(fn ($row) => [
                            'id' => $row->id,
                            'question_type_master_id' => $row->question_type_master_id,
                            'question_type' => $row->questionType?->slug,
                            'question_type_name' => $row->questionType?->name,
                            'difficulty' => $row->difficulty,
                            'bloom_level' => null,
                            'question_count' => $row->question_count,
                            'marks_per_question' => $row->marks_per_question,
                            'available_questions' => $row->available_questions ?? 0,
                        ]),
                    ];
                })
        );

        return $blueprint;
    }

    public function index(Request $request)
    {
        $query = PaperBlueprint::with($this->relationships())->latest();

        if ($request->filled('grade_id')) $query->where('grade_id', $request->grade_id);
        if ($request->filled('stream_id')) $query->where('stream_id', $request->stream_id);
        if ($request->filled('subject_id')) $query->where('subject_id', $request->subject_id);
        if ($request->filled('exam_name_id')) $query->where('exam_name_id', $request->exam_name_id);
        if ($request->filled('is_active')) $query->where('is_active', $request->boolean('is_active'));

        return response()->json($query->get()->map(fn ($bp) => $this->formatBlueprint($bp)));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'grade_id' => 'required|exists:grades,id',
            'stream_id' => 'nullable|exists:streams,id',
            'subject_id' => 'required|exists:subjects,id',
            'exam_name_id' => 'nullable|exists:exam_names,id',
            'duration_minutes' => 'nullable|integer',
            'duration' => 'nullable|integer',
            'total_marks' => 'required|numeric',
            'is_active' => 'boolean',
            'sections' => 'required|array|min:1',
            'sections.*.section_name' => 'required|string|max:255',
            'sections.*.instructions' => 'nullable|string',
            'sections.*.items' => 'required|array|min:1',
            'sections.*.items.*.question_type' => 'required_without:sections.*.items.*.question_type_master_id',
            'sections.*.items.*.question_type_master_id' => 'nullable|exists:question_type_masters,id',
            'sections.*.items.*.difficulty' => 'nullable|string|max:255',
            'sections.*.items.*.question_count' => 'required|integer|min:1',
            'sections.*.items.*.marks_per_question' => 'required|numeric|min:0',
        ]);

        return DB::transaction(function () use ($data) {
            $blueprint = PaperBlueprint::create([
                'name' => $data['name'],
                'grade_id' => $data['grade_id'],
                'stream_id' => $data['stream_id'] ?? null,
                'subject_id' => $data['subject_id'],
                'exam_name_id' => $data['exam_name_id'] ?? null,
                'duration_minutes' => $data['duration_minutes'] ?? $data['duration'] ?? null,
                'total_marks' => $data['total_marks'],
                'is_active' => $data['is_active'] ?? true,
            ]);

            foreach ($data['sections'] as $sectionIndex => $section) {
                foreach ($section['items'] as $itemIndex => $item) {
                    $questionTypeId = $item['question_type_master_id'] ?? $this->resolveQuestionTypeId($item['question_type'] ?? null);

                    if (!$questionTypeId) continue;

                    $blueprint->sections()->create([
                        'section_name' => $section['section_name'],
                        'instructions' => $section['instructions'] ?? null,
                        'question_type_master_id' => $questionTypeId,
                        'difficulty' => $item['difficulty'] ?? null,
                        'question_count' => $item['question_count'],
                        'marks_per_question' => $item['marks_per_question'],
                        'sort_order' => (($sectionIndex + 1) * 100) + ($itemIndex + 1),
                    ]);
                }
            }

            return response()->json([
                'message' => 'Paper blueprint created successfully',
                'data' => $this->formatBlueprint($blueprint->load($this->relationships())),
            ], 201);
        });
    }

    public function show($id)
    {
        return response()->json($this->formatBlueprint(PaperBlueprint::with($this->relationships())->findOrFail($id)));
    }

    public function update(Request $request, $id)
    {
        $blueprint = PaperBlueprint::findOrFail($id);
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'grade_id' => 'required|exists:grades,id',
            'stream_id' => 'nullable|exists:streams,id',
            'subject_id' => 'required|exists:subjects,id',
            'exam_name_id' => 'nullable|exists:exam_names,id',
            'duration_minutes' => 'nullable|integer',
            'duration' => 'nullable|integer',
            'total_marks' => 'required|numeric',
            'is_active' => 'boolean',
            'sections' => 'required|array|min:1',
        ]);

        return DB::transaction(function () use ($blueprint, $data) {
            $blueprint->update([
                'name' => $data['name'],
                'grade_id' => $data['grade_id'],
                'stream_id' => $data['stream_id'] ?? null,
                'subject_id' => $data['subject_id'],
                'exam_name_id' => $data['exam_name_id'] ?? null,
                'duration_minutes' => $data['duration_minutes'] ?? $data['duration'] ?? null,
                'total_marks' => $data['total_marks'],
                'is_active' => $data['is_active'] ?? true,
            ]);

            $blueprint->sections()->delete();

            foreach ($data['sections'] as $sectionIndex => $section) {
                foreach (($section['items'] ?? []) as $itemIndex => $item) {
                    $questionTypeId = $item['question_type_master_id'] ?? $this->resolveQuestionTypeId($item['question_type'] ?? null);
                    if (!$questionTypeId) continue;
                    $blueprint->sections()->create([
                        'section_name' => $section['section_name'],
                        'instructions' => $section['instructions'] ?? null,
                        'question_type_master_id' => $questionTypeId,
                        'difficulty' => $item['difficulty'] ?? null,
                        'question_count' => $item['question_count'],
                        'marks_per_question' => $item['marks_per_question'],
                        'sort_order' => (($sectionIndex + 1) * 100) + ($itemIndex + 1),
                    ]);
                }
            }

            return response()->json([
                'message' => 'Paper blueprint updated successfully',
                'data' => $this->formatBlueprint($blueprint->fresh()->load($this->relationships())),
            ]);
        });
    }

    public function destroy($id)
    {
        PaperBlueprint::findOrFail($id)->delete();
        return response()->json(['message' => 'Paper blueprint deleted successfully']);
    }

    public function status($id)
    {
        $blueprint = PaperBlueprint::findOrFail($id);
        $blueprint->update(['is_active' => !$blueprint->is_active]);
        return response()->json(['message' => 'Blueprint status updated successfully', 'data' => $blueprint]);
    }

    public function dropdown(Request $request)
    {
        return $this->index($request);
    }

    private function availableQuestionCount($blueprint, $section): int
    {
        return Question::where('status', 'approved')
            ->where('grade_id', $blueprint->grade_id)
            ->where('subject_id', $blueprint->subject_id)
            ->where('question_type_master_id', $section->question_type_master_id)
            ->when($blueprint->stream_id, fn ($q) => $q->where('stream_id', $blueprint->stream_id))
            ->when($section->difficulty, fn ($q) => $q->where('difficulty', $section->difficulty))
            ->count();
    }
}
