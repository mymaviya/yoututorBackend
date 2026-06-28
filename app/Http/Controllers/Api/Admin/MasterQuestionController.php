<?php

namespace App\Http\Controllers\Api\Admin;

use App\Exports\MasterQuestionImportTemplateExport;
use App\Http\Controllers\Controller;
use App\Imports\MasterQuestionImport;
use App\Models\MasterQuestion;
use App\Models\Question;
use App\Models\QuestionImage;
use App\Models\QuestionMatchPair;
use App\Models\QuestionOption;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

class MasterQuestionController extends Controller
{
    private array $withRelations = [
        'package',
        'grade',
        'stream',
        'subject',
        'lesson',
        'type',
        'options',
        'matchPairs',
        'images',
    ];

    public function index(Request $request)
    {
        $query = MasterQuestion::with($this->withRelations);

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

        $question = DB::transaction(function () use ($request, $data) {
            $question = MasterQuestion::create($data + [
                'source' => 'platform',
                'is_active' => $request->boolean('is_active', true),
            ]);

            $this->syncOptions($request, $question);
            $this->syncMatchPairs($request, $question);
            $this->syncImages($request, $question);

            return $question;
        });

        return response()->json([
            'success' => true,
            'message' => 'Master question created successfully.',
            'data' => $question->fresh()->load($this->withRelations),
        ], 201);
    }

    public function show(MasterQuestion $masterQuestion)
    {
        $masterQuestion->load($this->withRelations);

        $data = $masterQuestion->toArray();
        $data['question_image'] = $masterQuestion->images->first()?->image_path;

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    public function update(Request $request, MasterQuestion $masterQuestion)
    {
        $data = $this->validatedData($request);

        DB::transaction(function () use ($request, $masterQuestion, $data) {
            $masterQuestion->update($data + [
                'is_active' => $request->boolean('is_active', $masterQuestion->is_active),
            ]);

            $this->syncOptions($request, $masterQuestion);
            $this->syncMatchPairs($request, $masterQuestion);
            $this->syncImages($request, $masterQuestion);
        });

        return response()->json([
            'success' => true,
            'message' => 'Master question updated successfully.',
            'data' => $masterQuestion->fresh()->load($this->withRelations),
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

        $masterQuestions = MasterQuestion::with(['options', 'matchPairs', 'images'])
            ->whereIn('id', $data['master_question_ids'])
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

                $question = Question::create([
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

                foreach ($masterQuestion->options as $option) {
                    QuestionOption::create([
                        'question_id' => $question->id,
                        'option_text' => $option->option_text,
                        'option_image' => $option->option_image,
                        'is_correct' => $option->is_correct,
                        'sort_order' => $option->sort_order,
                    ]);
                }

                foreach ($masterQuestion->matchPairs as $pair) {
                    QuestionMatchPair::create([
                        'question_id' => $question->id,
                        'left_value' => $pair->left_value,
                        'right_value' => $pair->right_value,
                        'sort_order' => $pair->sort_order,
                    ]);
                }

                foreach ($masterQuestion->images as $image) {
                    QuestionImage::create([
                        'question_id' => $question->id,
                        'image_path' => $image->image_path,
                    ]);
                }

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

            'options' => ['nullable', 'array'],
            'options.*.id' => ['nullable', 'exists:master_question_options,id'],
            'options.*.option_text' => ['nullable', 'string'],
            'options.*.option_image' => ['nullable', 'image', 'max:4096'],
            'options.*.old_option_image' => ['nullable', 'string'],
            'options.*.is_correct' => ['nullable', 'boolean'],

            'matches' => ['nullable'],
            'question_image' => ['nullable', 'image', 'max:4096'],
        ]);
    }

    private function syncOptions(Request $request, MasterQuestion $question): void
    {
        if (!$request->has('options')) {
            return;
        }

        $question->options()->delete();

        foreach ($request->input('options', []) as $index => $option) {
            $optionText = trim((string) ($option['option_text'] ?? ''));
            $imagePath = $option['old_option_image'] ?? null;

            if ($request->hasFile("options.{$index}.option_image")) {
                $imagePath = $request->file("options.{$index}.option_image")
                    ->store('master-question-options', 'public');
            }

            if ($optionText === '' && !$imagePath) {
                continue;
            }

            $question->options()->create([
                'option_text' => $optionText,
                'option_image' => $imagePath,
                'is_correct' => (bool) ($option['is_correct'] ?? false),
                'sort_order' => $index,
            ]);
        }
    }

    private function syncMatchPairs(Request $request, MasterQuestion $question): void
    {
        if (!$request->filled('matches')) {
            return;
        }

        $matches = $request->input('matches');

        if (is_string($matches)) {
            $matches = json_decode($matches, true) ?: [];
        }

        $question->matchPairs()->delete();

        foreach ($matches as $index => $match) {
            $leftValue = trim((string) ($match['left'] ?? $match['left_value'] ?? ''));
            $rightValue = trim((string) ($match['right'] ?? $match['right_value'] ?? ''));

            if ($leftValue === '' && $rightValue === '') {
                continue;
            }

            $question->matchPairs()->create([
                'left_value' => $leftValue,
                'right_value' => $rightValue,
                'sort_order' => $index,
            ]);
        }
    }

    private function syncImages(Request $request, MasterQuestion $question): void
    {
        if (!$request->hasFile('question_image')) {
            return;
        }

        $path = $request->file('question_image')
            ->store('master-question-images', 'public');

        $question->images()->delete();

        $question->images()->create([
            'image_path' => $path,
        ]);
    }
}
