<?php

namespace App\Http\Controllers\Api;

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
        return [
            'grade',
            'stream',
            'subject',
            'examName',
            'sections.questionType',
            'sections.bloomLevels',
        ];
    }

    private function resolveQuestionTypeId($value): ?int
    {
        if (is_numeric($value)) {
            return (int) $value;
        }

        return QuestionTypeMaster::where('slug', $value)
            ->orWhere('name', $value)
            ->value('id');
    }

    private function calculateBloomCount($percentage, $questionCount): int
    {
        return (int) round(((float) $percentage / 100) * (int) $questionCount);
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
                        'items' => $rows->values()->map(fn($row) => [
                            'id' => $row->id,
                            'question_type_master_id' => $row->question_type_master_id,
                            'question_type' => $row->questionType?->slug,
                            'question_type_name' => $row->questionType?->name,
                            'difficulty' => $row->difficulty,
                            'question_count' => (int) $row->question_count,
                            'marks_per_question' => (float) $row->marks_per_question,
                            'available_questions' => $row->available_questions ?? 0,
                            'bloom_levels' => $row->bloomLevels->map(fn($bloom) => [
                                'id' => $bloom->id,
                                'bloom_level' => $bloom->bloom_level,
                                'percentage' => (float) $bloom->percentage,
                                'calculated_count' => (int) $bloom->calculated_count,
                            ])->values(),
                        ]),
                    ];
                })
        );

        return $blueprint;
    }

    public function index(Request $request)
    {
        $query = PaperBlueprint::with($this->relationships())->latest();

        $user = auth()->user();
        $userRole = $user->roleData?->slug ?? $user->role;

        if (!in_array($userRole, ['superadmin', 'super_admin'])) {
            $query->where('subscription_id', $user->subscription_id);
        }


        if ($request->filled('grade_id')) {
            $query->where('grade_id', $request->grade_id);
        }

        if ($request->filled('stream_id')) {
            $query->where('stream_id', $request->stream_id);
        }

        if ($request->filled('subject_id')) {
            $query->where('subject_id', $request->subject_id);
        }

        if ($request->filled('exam_name_id')) {
            $query->where('exam_name_id', $request->exam_name_id);
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        return response()->json(
            $query->get()->map(fn($bp) => $this->formatBlueprint($bp))
        );
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

            'use_bloom_levels' => 'nullable|boolean',
            'bloom_levels' => 'nullable|array',
            'bloom_levels.*.bloom_level' => 'required_with:bloom_levels|in:remember,understand,apply,analyze,evaluate,create',
            'bloom_levels.*.percentage' => 'required_with:bloom_levels|numeric|min:0|max:100',
        ]);

        return DB::transaction(function () use ($data) {
            $blueprint = PaperBlueprint::create([
                'subscription_id' => auth()->user()?->subscription_id,
                'name' => $data['name'],
                'grade_id' => $data['grade_id'],
                'stream_id' => $data['stream_id'] ?? null,
                'subject_id' => $data['subject_id'],
                'exam_name_id' => $data['exam_name_id'] ?? null,
                'duration_minutes' => $data['duration_minutes'] ?? $data['duration'] ?? null,
                'total_marks' => $data['total_marks'],
                'is_active' => $data['is_active'] ?? true,
            ]);

            $totalQuestions = $this->calculateTotalQuestions($data['sections']);

            $useBloomLevels = (bool) ($data['use_bloom_levels'] ?? false);

            $globalBloomLevels = $useBloomLevels
                ? collect($data['bloom_levels'] ?? [])
                ->filter(function ($bloom) {
                    return !empty($bloom['bloom_level'])
                        && isset($bloom['percentage'])
                        && (float) $bloom['percentage'] > 0;
                })
                ->values()
                ->toArray()
                : [];

            $globalBloomCounts = $this->calculateGlobalBloomCounts(
                $globalBloomLevels,
                $totalQuestions
            );

            $bloomSaved = false;

            foreach ($data['sections'] as $sectionIndex => $section) {
                foreach ($section['items'] as $itemIndex => $item) {
                    $questionTypeId = $item['question_type_master_id']
                        ?? $this->resolveQuestionTypeId($item['question_type'] ?? null);

                    if (!$questionTypeId) {
                        continue;
                    }

                    $blueprintSection = $blueprint->sections()->create([
                        'section_name' => $section['section_name'],
                        'instructions' => $section['instructions'] ?? null,
                        'question_type_master_id' => $questionTypeId,
                        'difficulty' => $item['difficulty'] ?? null,
                        'question_count' => $item['question_count'],
                        'marks_per_question' => $item['marks_per_question'],
                        'sort_order' => (($sectionIndex + 1) * 100) + ($itemIndex + 1),
                    ]);

                    if (!$bloomSaved && count($globalBloomLevels)) {
                        foreach ($globalBloomLevels as $bloom) {
                            $level = strtolower($bloom['bloom_level']);
                            $percentage = (float) ($bloom['percentage'] ?? 0);

                            $blueprintSection->bloomLevels()->create([
                                'bloom_level' => $level,
                                'percentage' => $percentage,
                                'calculated_count' => $globalBloomCounts[$level] ?? 0,
                            ]);
                        }

                        $bloomSaved = true;
                    }
                }
            }

            return response()->json([
                'message' => 'Paper blueprint created successfully',
                'data' => $this->formatBlueprint($blueprint->fresh()->load($this->relationships())),
            ], 201);
        });
    }

    public function show($id)
    {
        $blueprint = PaperBlueprint::with($this->relationships())->findOrFail($id);

        if ($response = $this->ensureBlueprintAccess($blueprint)) {
            return $response;
        }

        return response()->json(
            $this->formatBlueprint($blueprint)
        );
    }

    public function update(Request $request, $id)
    {
        $blueprint = PaperBlueprint::findOrFail($id);

        if ($response = $this->ensureBlueprintAccess($blueprint)) {
            return $response;
        }

        $data = $request->validate([
            'name' => 'nullable|string|max:255',
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

            'use_bloom_levels' => 'nullable|boolean',
            'bloom_levels.*.bloom_level' => 'required_with:bloom_levels|in:remember,understand,apply,analyze,evaluate,create',
            'bloom_levels.*.percentage' => 'required_with:bloom_levels|numeric|min:0|max:100',

        ]);

        return DB::transaction(function () use ($blueprint, $data) {
            $blueprint->update([
                'name' => $data['name'] ?? $blueprint->name,
                'grade_id' => $data['grade_id'],
                'stream_id' => $data['stream_id'] ?? null,
                'subject_id' => $data['subject_id'],
                'exam_name_id' => $data['exam_name_id'] ?? null,
                'duration_minutes' => $data['duration_minutes'] ?? $data['duration'] ?? null,
                'total_marks' => $data['total_marks'],
                'is_active' => $data['is_active'] ?? true,
            ]);

            $sectionIds = $blueprint->sections()->pluck('id');

            DB::table('paper_blueprint_bloom_levels')
                ->whereIn('paper_blueprint_section_id', $sectionIds)
                ->delete();

            $blueprint->sections()->delete();

            $totalQuestions = $this->calculateTotalQuestions($data['sections']);

            $useBloomLevels = (bool) ($data['use_bloom_levels'] ?? false);

            $globalBloomLevels = $useBloomLevels
                ? collect($data['bloom_levels'] ?? [])
                ->filter(function ($bloom) {
                    return !empty($bloom['bloom_level'])
                        && isset($bloom['percentage'])
                        && (float) $bloom['percentage'] > 0;
                })
                ->values()
                ->toArray()
                : [];

            $globalBloomCounts = $this->calculateGlobalBloomCounts(
                $globalBloomLevels,
                $totalQuestions
            );

            $bloomSaved = false;

            foreach ($data['sections'] as $sectionIndex => $section) {
                foreach ($section['items'] as $itemIndex => $item) {
                    $questionTypeId = $item['question_type_master_id']
                        ?? $this->resolveQuestionTypeId($item['question_type'] ?? null);

                    if (!$questionTypeId) {
                        continue;
                    }

                    $blueprintSection = $blueprint->sections()->create([
                        'section_name' => $section['section_name'],
                        'instructions' => $section['instructions'] ?? null,
                        'question_type_master_id' => $questionTypeId,
                        'difficulty' => $item['difficulty'] ?? null,
                        'question_count' => $item['question_count'],
                        'marks_per_question' => $item['marks_per_question'],
                        'sort_order' => (($sectionIndex + 1) * 100) + ($itemIndex + 1),
                    ]);

                    if (!$bloomSaved && count($globalBloomLevels)) {
                        foreach ($globalBloomLevels as $bloom) {
                            $level = strtolower($bloom['bloom_level']);
                            $percentage = (float) ($bloom['percentage'] ?? 0);

                            $blueprintSection->bloomLevels()->create([
                                'bloom_level' => $level,
                                'percentage' => $percentage,
                                'calculated_count' => $globalBloomCounts[$level] ?? 0,
                            ]);
                        }

                        $bloomSaved = true;
                    }
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
        $blueprint = PaperBlueprint::findOrFail($id);

        if ($response = $this->ensureBlueprintAccess($blueprint)) {
            return $response;
        }

        $blueprint->delete();

        return response()->json([
            'message' => 'Paper blueprint deleted successfully',
        ]);
    }

    public function status($id)
    {
        $blueprint = PaperBlueprint::findOrFail($id);

        if ($response = $this->ensureBlueprintAccess($blueprint)) {
            return $response;
        }

        $blueprint->update([
            'is_active' => !$blueprint->is_active,
        ]);

        return response()->json([
            'message' => 'Blueprint status updated successfully',
            'data' => $blueprint,
        ]);
    }

    public function dropdown(Request $request)
    {
        return $this->index($request);
    }

    private function availableQuestionCount($blueprint, $section): int
    {
        $query = Question::where('status', 'approved');

        $user = auth()->user();
        $userRole = $user->roleData?->slug ?? $user->role;

        if (!in_array($userRole, ['superadmin', 'super_admin'])) {
            $query->where('subscription_id', $user->subscription_id);
        }

        return $query->where('grade_id', $blueprint->grade_id)
            ->where('subject_id', $blueprint->subject_id)
            ->where('question_type_master_id', $section->question_type_master_id)
            ->when($blueprint->stream_id, fn($q) => $q->where('stream_id', $blueprint->stream_id))
            ->when($section->difficulty, fn($q) => $q->where('difficulty', $section->difficulty))
            ->count();
    }

    private function calculateTotalQuestions(array $sections): int
    {
        $total = 0;

        foreach ($sections as $section) {
            foreach (($section['items'] ?? []) as $item) {
                $total += (int) ($item['question_count'] ?? 0);
            }
        }

        return $total;
    }

    private function calculateGlobalBloomCounts(array $bloomLevels, int $totalQuestions): array
    {
        $counts = [];
        $used = 0;

        foreach ($bloomLevels as $index => $bloom) {
            $percentage = (float) ($bloom['percentage'] ?? 0);
            $count = (int) floor(($percentage / 100) * $totalQuestions);

            $counts[$bloom['bloom_level']] = $count;
            $used += $count;
        }

        $remaining = $totalQuestions - $used;

        if ($remaining > 0 && !empty($bloomLevels[0]['bloom_level'])) {
            $firstLevel = $bloomLevels[0]['bloom_level'];
            $counts[$firstLevel] += $remaining;
        }

        return $counts;
    }

    private function ensureBlueprintAccess(PaperBlueprint $blueprint)
    {
        $user = auth()->user();
        $userRole = $user->roleData?->slug ?? $user->role;

        if (in_array($userRole, ['superadmin', 'super_admin'])) {
            return null;
        }

        if ($blueprint->subscription_id !== $user->subscription_id) {
            return response()->json([
                'message' => 'You are not allowed to access this blueprint.',
            ], 403);
        }

        return null;
    }

    public function list()
    {
        return response()->json([
            'success' => true,
            'data' => PaperBlueprint::where(
                'subscription_id',
                auth()->user()->subscription_id
            )
                ->where('is_active', true)
                ->orderBy('name')
                ->get([
                    'id',
                    'name',
                    'grade_id',
                    'subject_id',
                ]),
        ]);
    }
}
