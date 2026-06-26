<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\MasterQuestion;
use App\Models\Question;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Exports\MasterQuestionImportTemplateExport;
use App\Imports\MasterQuestionImport;
use Maatwebsite\Excel\Facades\Excel;

class MasterQuestionController extends Controller
{
    public function index(Request $request)
    {
        $query = MasterQuestion::with([
            'package',
            'grade',
            'stream',
            'subject',
            'lesson',
            'type',
        ]);

        if ($request->filled('question_bank_package_id')) {
            $query->where('question_bank_package_id', $request->question_bank_package_id);
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

        if ($request->filled('lesson_id')) {
            $query->where('lesson_id', $request->lesson_id);
        }

        if ($request->filled('question_type_master_id')) {
            $query->where('question_type_master_id', $request->question_type_master_id);
        }

        if ($request->filled('difficulty')) {
            $query->where('difficulty', $request->difficulty);
        }

        if ($request->filled('bloom_level')) {
            $query->where('bloom_level', $request->bloom_level);
        }

        if ($request->filled('status')) {
            $query->where('is_active', $request->status === 'active');
        }

        if ($request->filled('search')) {
            $query->where('question', 'like', '%' . $request->search . '%');
        }

        return response()->json([
            'success' => true,
            'data' => $query
                ->latest()
                ->paginate((int) $request->input('per_page', 20)),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validatedData($request);

        $question = MasterQuestion::create($data + [
            'source' => 'platform',
            'is_active' => $data['is_active'] ?? true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Master question created successfully.',
            'data' => $question->load([
                'package',
                'grade',
                'stream',
                'subject',
                'lesson',
                'type',
            ]),
        ], 201);
    }

    public function show(MasterQuestion $masterQuestion)
    {
        return response()->json([
            'success' => true,
            'data' => $masterQuestion->load([
                'package',
                'grade',
                'stream',
                'subject',
                'lesson',
                'type',
            ]),
        ]);
    }

    public function update(Request $request, MasterQuestion $masterQuestion)
    {
        $data = $this->validatedData($request);

        $masterQuestion->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Master question updated successfully.',
            'data' => $masterQuestion->fresh()->load([
                'package',
                'grade',
                'stream',
                'subject',
                'lesson',
                'type',
            ]),
        ]);
    }

    public function destroy(MasterQuestion $masterQuestion)
    {
        $masterQuestion->delete();

        return response()->json([
            'success' => true,
            'message' => 'Master question deleted successfully.',
        ]);
    }

    public function importToSchool(Request $request)
    {
        $data = $request->validate([
            'master_question_ids' => ['required', 'array', 'min:1'],
            'master_question_ids.*' => ['required', 'exists:master_questions,id'],
        ]);

        $subscriptionId = auth()->user()?->subscription_id;

        if (!$subscriptionId) {
            return response()->json([
                'message' => 'No subscription assigned to your account.',
            ], 403);
        }

        $masterQuestions = MasterQuestion::whereIn('id', $data['master_question_ids'])
            ->where('is_active', true)
            ->get();

        $created = 0;
        $skipped = 0;

        DB::transaction(function () use ($masterQuestions, $subscriptionId, &$created, &$skipped) {
            foreach ($masterQuestions as $masterQuestion) {
                $exists = Question::where('subscription_id', $subscriptionId)
                    ->where('grade_id', $masterQuestion->grade_id)
                    ->where('stream_id', $masterQuestion->stream_id)
                    ->where('subject_id', $masterQuestion->subject_id)
                    ->where('lesson_id', $masterQuestion->lesson_id)
                    ->where('question_type_master_id', $masterQuestion->question_type_master_id)
                    ->where('question', $masterQuestion->question)
                    ->exists();

                if ($exists) {
                    $skipped++;
                    continue;
                }

                Question::create([
                    'subscription_id' => $subscriptionId,
                    'grade_id' => $masterQuestion->grade_id,
                    'stream_id' => $masterQuestion->stream_id,
                    'subject_id' => $masterQuestion->subject_id,
                    'lesson_id' => $masterQuestion->lesson_id,
                    'question_type_master_id' => $masterQuestion->question_type_master_id,
                    'question' => $masterQuestion->question,
                    'difficulty' => $masterQuestion->difficulty,
                    'bloom_level' => $masterQuestion->bloom_level,
                    'marks' => $masterQuestion->marks,
                    'answer' => $masterQuestion->answer,
                    'explanation' => $masterQuestion->explanation,
                    'status' => 'approved',
                    'approved_by' => auth()->id(),
                    'approved_at' => now(),
                    'created_by' => auth()->id(),
                    'is_active' => true,
                ]);

                $created++;
            }
        });

        return response()->json([
            'success' => true,
            'message' => 'Master questions imported successfully.',
            'created' => $created,
            'skipped' => $skipped,
        ]);
    }

    public function downloadTemplate()
    {
        return Excel::download(
            new MasterQuestionImportTemplateExport(),
            'master_question_import_template.xlsx'
        );
    }

    public function import(Request $request)
    {
        $data = $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv'],
            'question_bank_package_id' => ['nullable', 'exists:question_bank_packages,id'],
        ]);

        $import = new MasterQuestionImport(
            $data['question_bank_package_id'] ?? null
        );

        Excel::import($import, $request->file('file'));

        return response()->json([
            'success' => true,
            'message' => 'Master question import completed.',
            'imported' => $import->imported,
            'skipped' => $import->skipped,
            'errors' => $import->errors,
        ]);
    }

    private function validatedData(Request $request): array
    {
        return $request->validate([
            'question_bank_package_id' => ['nullable', 'exists:question_bank_packages,id'],
            'grade_id' => ['required', 'exists:grades,id'],
            'stream_id' => ['nullable', 'exists:streams,id'],
            'subject_id' => ['required', 'exists:subjects,id'],
            'lesson_id' => ['nullable', 'exists:lessons,id'],
            'question_type_master_id' => ['required', 'exists:question_type_masters,id'],

            'question' => ['required', 'string'],
            'difficulty' => ['required', 'string', 'max:50'],
            'bloom_level' => ['nullable', 'string', 'max:50'],
            'marks' => ['required', 'numeric', 'min:0'],
            'answer' => ['nullable', 'string'],
            'explanation' => ['nullable', 'string'],

            'language' => ['nullable', 'string', 'max:20'],
            'is_active' => ['nullable', 'boolean'],
        ]);
    }
}
