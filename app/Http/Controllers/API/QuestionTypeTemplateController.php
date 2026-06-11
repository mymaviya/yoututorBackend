<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\QuestionTypeAssignment;
use App\Models\QuestionTypeMaster;
use App\Models\QuestionTypeTemplate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class QuestionTypeTemplateController extends Controller
{
    public function index()
    {
        return QuestionTypeTemplate::with(['items.questionType'])
            ->latest()
            ->get();
    }

    public function show(QuestionTypeTemplate $questionTypeTemplate)
    {
        return $questionTypeTemplate->load(['items.questionType']);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                'unique:question_type_templates,name',
            ],
            'category' => [
                'nullable',
                'string',
                'max:255',
            ],
            'question_type_master_ids' => [
                'required',
                'array',
                'min:1',
            ],
            'question_type_master_ids.*' => [
                'required',
                'exists:question_type_masters,id',
            ],
            'is_active' => [
                'nullable',
                'boolean',
            ],
        ]);

        return DB::transaction(function () use ($validated) {
            $template = QuestionTypeTemplate::create([
                'name' => $validated['name'],
                'category' => $validated['category'] ?? null,
                'is_active' => $validated['is_active'] ?? true,
            ]);

            foreach ($validated['question_type_master_ids'] as $questionTypeMasterId) {
                $template->items()->create([
                    'question_type_master_id' => $questionTypeMasterId,
                ]);
            }

            return response()->json([
                'message' => 'Question type template created successfully',
                'data' => $template->load(['items.questionType']),
            ], 201);
        });
    }

    public function update(Request $request, QuestionTypeTemplate $questionTypeTemplate)
    {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('question_type_templates', 'name')
                    ->ignore($questionTypeTemplate->id),
            ],
            'category' => [
                'nullable',
                'string',
                'max:255',
            ],
            'question_type_master_ids' => [
                'required',
                'array',
                'min:1',
            ],
            'question_type_master_ids.*' => [
                'required',
                'exists:question_type_masters,id',
            ],
            'is_active' => [
                'nullable',
                'boolean',
            ],
        ]);

        return DB::transaction(function () use ($validated, $questionTypeTemplate) {
            $questionTypeTemplate->update([
                'name' => $validated['name'],
                'category' => $validated['category'] ?? null,
                'is_active' => $validated['is_active'] ?? true,
            ]);

            $questionTypeTemplate->items()->delete();

            foreach ($validated['question_type_master_ids'] as $questionTypeMasterId) {
                $questionTypeTemplate->items()->create([
                    'question_type_master_id' => $questionTypeMasterId,
                ]);
            }

            return response()->json([
                'message' => 'Question type template updated successfully',
                'data' => $questionTypeTemplate->load(['items.questionType']),
            ]);
        });
    }

    public function destroy(QuestionTypeTemplate $questionTypeTemplate)
    {
        $questionTypeTemplate->delete();

        return response()->json([
            'message' => 'Question type template deleted successfully',
        ]);
    }

    public function apply(Request $request, QuestionTypeTemplate $questionTypeTemplate)
    {
        $validated = $request->validate([
            'grade_ids' => [
                'required',
                'array',
                'min:1',
            ],
            'grade_ids.*' => [
                'required',
                'exists:grades,id',
            ],
            'stream_id' => [
                'nullable',
                'exists:streams,id',
            ],
            'subject_ids' => [
                'required',
                'array',
                'min:1',
            ],
            'subject_ids.*' => [
                'required',
                'exists:subjects,id',
            ],
        ]);

        $created = 0;
        $skipped = 0;

        DB::transaction(function () use (
            $questionTypeTemplate,
            $validated,
            &$created,
            &$skipped
        ) {
            $questionTypeTemplate->load('items');

            foreach ($validated['grade_ids'] as $gradeId) {
                foreach ($validated['subject_ids'] as $subjectId) {
                    foreach ($questionTypeTemplate->items as $item) {
                        $exists = QuestionTypeAssignment::where('grade_id', $gradeId)
                            ->where('stream_id', $validated['stream_id'] ?? null)
                            ->where('subject_id', $subjectId)
                            ->where('question_type_master_id', $item->question_type_master_id)
                            ->exists();

                        if ($exists) {
                            $skipped++;
                            continue;
                        }

                        QuestionTypeAssignment::create([
                            'grade_id' => $gradeId,
                            'stream_id' => $validated['stream_id'] ?? null,
                            'subject_id' => $subjectId,
                            'question_type_master_id' => $item->question_type_master_id,
                            'is_active' => true,
                        ]);

                        $created++;
                    }
                }
            }
        });

        return response()->json([
            'message' => 'Question type template applied successfully',
            'created' => $created,
            'skipped' => $skipped,
        ]);
    }

    public function masters()
    {
        return QuestionTypeMaster::where('is_active', true)
            ->orderBy('name')
            ->get();
    }
}