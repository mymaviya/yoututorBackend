<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\PaperBlueprint;
use App\Models\Question;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PaperBlueprintController extends Controller
{
    private function relationships(): array
    {
        return [
            'grade',
            'subject',
            'examName',
            'sections',
        ];
    }

    private function validationRules(): array
    {
        return [
            'grade_id' => 'required|exists:grades,id',
            'subject_id' => 'required|exists:subjects,id',
            'exam_name_id' => 'nullable|exists:exam_names,id',
            'title' => 'required|string|max:255',
            'is_active' => 'boolean',

            'sections' => 'required|array|min:1',
            'sections.*.section_name' => 'required|string|max:255',
            'sections.*.instructions' => 'nullable|string',
            'sections.*.items' => 'required|array|min:1',
            'sections.*.items.*.question_type' => 'required|string|max:255',
            'sections.*.items.*.difficulty' => 'nullable|string|max:255',
            'sections.*.items.*.bloom_level' => 'nullable|string|max:255',
            'sections.*.items.*.question_count' => 'required|integer|min:1',
            'sections.*.items.*.marks_per_question' => 'required|numeric|min:0.5',
        ];
    }

    private function flattenSections(array $sections): array
    {
        $rows = [];

        foreach ($sections as $sectionIndex => $section) {
            foreach ($section['items'] as $itemIndex => $item) {
                $rows[] = [
                    'section_name' => $section['section_name'],
                    'instructions' => $section['instructions'] ?? null,
                    'question_type' => $item['question_type'],
                    'difficulty' => $item['difficulty'] ?? null,
                    'bloom_level' => $item['bloom_level'] ?? null,
                    'question_count' => $item['question_count'],
                    'marks_per_question' => $item['marks_per_question'],
                    'sort_order' => (($sectionIndex + 1) * 100) + $itemIndex,
                ];
            }
        }

        return $rows;
    }

    private function formatBlueprint(PaperBlueprint $blueprint): PaperBlueprint
    {
        $blueprint->setRelation(
            'sections',
            $blueprint->sections
                ->groupBy(function ($row) {
                    $sortOrder = (int) $row->sort_order;

                    return $sortOrder >= 100 ? intdiv($sortOrder, 100) : $sortOrder;
                })
                ->values()
                ->map(function ($rows) {
                    $first = $rows->first();

                    return [
                        'id' => $first->id,
                        'section_name' => $first->section_name,
                        'instructions' => $first->instructions,
                        'items' => $rows->values()->map(fn($row) => [
                            'id' => $row->id,
                            'question_type' => $row->question_type,
                            'difficulty' => $row->difficulty,
                            'bloom_level' => $row->bloom_level,
                            'question_count' => $row->question_count,
                            'marks_per_question' => $row->marks_per_question,
                        ]),
                    ];
                })
        );

        return $blueprint;
    }

    public function index(Request $request)
    {
        $query = PaperBlueprint::with($this->relationships())->latest();

        if ($request->filled('grade_id')) {
            $query->where('grade_id', $request->grade_id);
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


        $blueprints = $query->paginate(20);

        $blueprints->getCollection()->transform(
            fn(PaperBlueprint $blueprint) => $this->formatBlueprint($blueprint)
        );

        return response()->json($blueprints);
    }

    public function store(Request $request)
    {
        $data = $request->validate($this->validationRules());

        return DB::transaction(function () use ($data) {
            $sections = $this->flattenSections($data['sections']);
            $totalQuestions = collect($sections)->sum('question_count');

            $totalMarks = collect($sections)->sum(function ($section) {
                return $section['question_count'] * $section['marks_per_question'];
            });

            $blueprint = PaperBlueprint::create([
                'grade_id' => $data['grade_id'],
                'subject_id' => $data['subject_id'],
                'exam_name_id' => $data['exam_name_id'] ?? null,
                'title' => $data['title'],
                'total_questions' => $totalQuestions,
                'total_marks' => $totalMarks,
                'is_active' => $data['is_active'] ?? true,
            ]);

            foreach ($sections as $section) {
                $blueprint->sections()->create($section);
            }

            $blueprint->load($this->relationships());

            return response()->json([
                'message' => 'Paper blueprint created successfully',
                'data' => $this->formatBlueprint($blueprint),
            ], 201);
        });
    }

    public function show($id)
    {
        $blueprint = PaperBlueprint::with([
            'grade',
            'subject',
            'examName',
            'sections',
        ])->findOrFail($id);

        $blueprint = $this->attachAvailability($blueprint);

        return response()->json($blueprint);
    }


    public function update(Request $request, $id)
    {
        $blueprint = PaperBlueprint::findOrFail($id);

        $data = $request->validate($this->validationRules());

        return DB::transaction(function () use ($blueprint, $data) {
            $sections = $this->flattenSections($data['sections']);
            $totalQuestions = collect($sections)->sum('question_count');

            $totalMarks = collect($sections)->sum(function ($section) {
                return $section['question_count'] * $section['marks_per_question'];
            });

            $blueprint->update([
                'grade_id' => $data['grade_id'],
                'subject_id' => $data['subject_id'],
                'exam_name_id' => $data['exam_name_id'] ?? null,
                'title' => $data['title'],
                'total_questions' => $totalQuestions,
                'total_marks' => $totalMarks,
                'is_active' => $data['is_active'] ?? true,
            ]);

            $blueprint->sections()->delete();

            foreach ($sections as $section) {
                $blueprint->sections()->create($section);
            }

            $blueprint->load($this->relationships());

            return response()->json([
                'message' => 'Paper blueprint updated successfully',
                'data' => $this->formatBlueprint($blueprint),
            ]);
        });
    }

    public function destroy($id)
    {
        PaperBlueprint::findOrFail($id)->delete();

        return response()->json([
            'message' => 'Paper blueprint deleted successfully',
        ]);
    }

    public function status($id)
    {
        $blueprint = PaperBlueprint::findOrFail($id);

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
        $query = PaperBlueprint::with([
            'grade',
            'subject',
            'examName',
            'sections'
        ])
            ->where('is_active', true);

        if ($request->filled('grade_id')) {
            $query->where('grade_id', $request->grade_id);
        }

        if ($request->filled('subject_id')) {
            $query->where('subject_id', $request->subject_id);
        }

        if ($request->filled('exam_name_id')) {
            $query->where('exam_name_id', $request->exam_name_id);
        }

        $blueprints = $query->latest()->get();

        $blueprints->transform(function ($blueprint) {
            return $this->attachAvailability($blueprint);
        });

        return response()->json($blueprints);


    }


        private function availableQuestionCount($blueprint, $section)
        {
            return Question::where('status', 'approved')
                ->where('grade_id', $blueprint->grade_id)
                ->where('subject_id', $blueprint->subject_id)
                ->where('type', $section->question_type)
                ->when(!empty($section->difficulty), function ($q) use ($section) {
                    $q->where('difficulty', $section->difficulty);
                })
                ->when(!empty($section->bloom_level), function ($q) use ($section) {
                    $q->where('bloom_level', $section->bloom_level);
                })
                ->count();
        }

        private function attachAvailability($blueprint)
        {
            $blueprint->sections->transform(function ($section) use ($blueprint) {
                $section->available_questions = $this->availableQuestionCount(
                    $blueprint,
                    $section
                );

                return $section;
            });

            $blueprint->available_questions_total = $blueprint->sections
                ->sum('available_questions');

            return $blueprint;
        }




    }

