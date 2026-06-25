<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\QuestionTypeMaster;
use App\Models\TeacherQuestionTask;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Imports\QuestionImport;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\StreamedResponse;
use App\Exports\QuestionImportTemplateExport;


class QuestionController extends Controller
{
    private function isSuperAdmin(): bool
    {
        $user = auth()->user();
        $role = $user?->roleData?->slug ?? $user?->role;

        return in_array($role, ['superadmin', 'super_admin'], true);
    }

    private function ensureQuestionAccess(Question $question)
    {
        if ($this->isSuperAdmin()) {
            return null;
        }

        if ((int) $question->subscription_id !== (int) auth()->user()?->subscription_id) {
            return response()->json([
                'message' => 'You are not allowed to access this question.',
            ], 403);
        }

        return null;
    }

    private function resolveQuestionTypeId(Request $request): ?int
    {
        if ($request->filled('question_type_master_id')) {
            return (int) $request->question_type_master_id;
        }

        $type = $request->input('type') ?? $request->input('question_type');

        if (!$type) {
            return null;
        }

        if (is_numeric($type)) {
            return (int) $type;
        }

        return QuestionTypeMaster::where('slug', $type)->orWhere('name', $type)->value('id');
    }

    private function questionTypeSlug($question): ?string
    {
        return $question->type?->slug;
    }

    public function index(Request $request)
    {
        $query = Question::with(['grade', 'stream', 'subject', 'lesson', 'type', 'options', 'languageItems', 'matchPairs', 'creator']);

        $user = auth()->user();
        $userRole = $user->roleData?->slug ?? $user->role;

        if (!in_array($userRole, ['superadmin', 'super_admin'])) {
            $query->where('subscription_id', $user->subscription_id);
        }

        $isForPaper = $request->boolean('for_paper');

        if (auth()->check() && auth()->user()->role !== 'admin' && !$isForPaper) {
            $query->where('created_by', auth()->id());
        }

        if ($isForPaper) {
            $query->where('status', 'approved');
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
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
        } elseif ($request->filled('type')) {
            $typeId = QuestionTypeMaster::where('slug', $request->type)->orWhere('name', $request->type)->value('id');
            if ($typeId) {
                $query->where('question_type_master_id', $typeId);
            }
        }

        if ($request->filled('difficulty')) {
            $query->where('difficulty', $request->difficulty);
        }

        if ($request->filled('search')) {
            $query->where('question', 'like', '%' . $request->search . '%');
        }

        return $query->latest()->paginate((int) $request->input('per_page', 50));
    }

    public function store(Request $request)
    {
        $questionTypeId = $this->resolveQuestionTypeId($request);

        if (!$questionTypeId) {
            return response()->json([
                'message' => 'Invalid question type.',
                'errors' => ['type' => ['Invalid question type.']],
            ], 422);
        }

        $data = $request->validate([
            'grade_id' => 'required|exists:grades,id',
            'stream_id' => 'nullable|exists:streams,id',
            'subject_id' => 'required|exists:subjects,id',
            'lesson_id' => 'nullable|exists:lessons,id',
            'task_id' => 'nullable|exists:teacher_question_tasks,id',
            'question' => 'required',
            'difficulty' => 'required|string',
            'bloom_level' => 'nullable|string',
            'marks' => 'required|numeric',
            'answer' => 'nullable',
            'explanation' => 'nullable',
            'options' => 'nullable|array',
            'matches' => 'nullable',
            'content_items' => 'nullable',
            'question_image' => 'nullable|image|max:4096',
        ]);

        return DB::transaction(function () use ($request, $data, $questionTypeId) {
            if (auth()->user()->role === 'teacher') {
                $allowed = auth()->user()->teacherAssignments()
                    ->where('grade_id', $data['grade_id'])
                    ->where('subject_id', $data['subject_id'])
                    ->where(function ($q) use ($data) {
                        $q->whereNull('stream_id');

                        if (!empty($data['stream_id'])) {
                            $q->orWhere('stream_id', $data['stream_id']);
                        }
                    })
                    ->exists();

                if (!$allowed) {
                    return response()->json(['message' => 'You are not assigned to this grade, stream and subject.'], 403);
                }

                if ($request->filled('task_id')) {
                    $task = TeacherQuestionTask::where('id', $request->task_id)
                        ->where('teacher_id', auth()->id())
                        ->where('question_type_master_id', $questionTypeId)
                        ->first();

                    if (!$task) {
                        return response()->json(['message' => 'No assigned task found for this question type.'], 403);
                    }

                    $createdCount = Question::where('created_by', auth()->id())
                        ->where('grade_id', $task->grade_id)
                        ->where('subject_id', $task->subject_id)
                        ->where('question_type_master_id', $task->question_type_master_id)
                        ->where(function ($q) use ($task) {
                            $q->whereNull('stream_id');

                            if ($task->stream_id) {
                                $q->orWhere('stream_id', $task->stream_id);
                            }
                        })
                        ->when($task->lesson_id, fn($q) => $q->where('lesson_id', $task->lesson_id))
                        ->count();

                    if ($createdCount >= $task->target_count) {
                        return response()->json(['message' => "This task allows only {$task->target_count} questions."], 403);
                    }
                }
            }

            $question = Question::create([
                'subscription_id' => auth()->user()->subscription_id,
                'grade_id' => $data['grade_id'],
                'stream_id' => $data['stream_id'] ?? null,
                'subject_id' => $data['subject_id'],
                'lesson_id' => $data['lesson_id'] ?? null,
                'question_type_master_id' => $questionTypeId,
                'question' => $data['question'],
                'difficulty' => $data['difficulty'],
                'bloom_level' => $data['bloom_level'] ?? null,
                'marks' => $data['marks'],
                'answer' => $data['answer'] ?? null,
                'explanation' => $data['explanation'] ?? null,
                'created_by' => auth()->id(),
                'status' => auth()->user()->role === 'admin' ? 'approved' : 'pending',
            ]);

            if ($request->hasFile('question_image')) {
                $question->images()->create([
                    'image_path' => $request->file('question_image')->store('questions', 'public'),
                ]);
            }

            $typeSlug = $question->type?->slug;

            if (in_array($typeSlug, ['word_meaning', 'make_sentence', 'difficult_words'])) {
                foreach (json_decode($request->content_items, true) ?? [] as $item) {
                    if (!empty($item['word'])) {
                        $question->languageItems()->create([
                            'word' => $item['word'],
                            'answer' => $item['meaning'] ?? $item['sentence'] ?? $item['answer'] ?? null,
                        ]);
                    }
                }
            }

            if ($typeSlug === 'match_column' && $request->matches) {
                foreach (json_decode($request->matches, true) ?? [] as $index => $pair) {
                    $question->matchPairs()->create([
                        'left_value' => $pair['left'] ?? '',
                        'right_value' => $pair['right'] ?? '',
                        'sort_order' => $index + 1,
                    ]);
                }
            }

            foreach ($request->options ?? [] as $index => $opt) {
                $optionImage = null;

                if ($request->hasFile("options.$index.option_image")) {
                    $optionImage = $request->file("options.$index.option_image")->store('question-options', 'public');
                }

                QuestionOption::create([
                    'question_id' => $question->id,
                    'option_text' => $opt['option_text'] ?? '',
                    'option_image' => $optionImage,
                    'is_correct' => $opt['is_correct'] ?? false,
                    'sort_order' => $index + 1,
                ]);
            }

            User::where('role', 'admin')
                ->where('subscription_id', auth()->user()?->subscription_id)
                ->get()
                ->each(function ($admin) {
                    notifyUser($admin->id, 'New Question Submitted', 'A new question has been submitted for approval.', 'question_submitted', '/questions/approvals');
                });

            return response()->json([
                'message' => 'Question created successfully',
                'data' => $question->load(['grade', 'stream', 'subject', 'lesson', 'type', 'options', 'images', 'matchPairs']),
            ], 201);
        });
    }

    public function show($id)
    {
        $question = Question::with(['grade', 'stream', 'subject', 'lesson', 'type', 'options', 'images', 'matchPairs', 'languageItems', 'creator'])->findOrFail($id);

        if ($response = $this->ensureQuestionAccess($question)) {
            return $response;
        }

        return $question;
    }

    public function update(Request $request, $id)
    {
        $question = Question::findOrFail($id);

        if ($response = $this->ensureQuestionAccess($question)) {
            return $response;
        }

        $questionTypeId = $this->resolveQuestionTypeId($request) ?: $question->question_type_master_id;

        $data = $request->validate([
            'grade_id' => 'required|exists:grades,id',
            'stream_id' => 'nullable|exists:streams,id',
            'subject_id' => 'required|exists:subjects,id',
            'lesson_id' => 'nullable|exists:lessons,id',
            'question' => 'required',
            'difficulty' => 'required|string',
            'bloom_level' => 'nullable|string',
            'marks' => 'required|numeric',
            'answer' => 'nullable',
            'explanation' => 'nullable',
            'options' => 'nullable|array',
            'matches' => 'nullable',
        ]);

        return DB::transaction(function () use ($request, $question, $data, $questionTypeId) {
            $question->update([
                'grade_id' => $data['grade_id'],
                'stream_id' => $data['stream_id'] ?? null,
                'subject_id' => $data['subject_id'],
                'lesson_id' => $data['lesson_id'] ?? null,
                'question_type_master_id' => $questionTypeId,
                'question' => $data['question'],
                'difficulty' => $data['difficulty'],
                'bloom_level' => $data['bloom_level'] ?? null,
                'marks' => $data['marks'],
                'answer' => $data['answer'] ?? null,
                'explanation' => $data['explanation'] ?? null,
                'status' => auth()->user()->role === 'admin' ? $question->status : 'pending',
            ]);

            if ($request->has('options')) {
                $question->options()->delete();
                foreach ($request->options ?? [] as $index => $opt) {
                    $question->options()->create([
                        'option_text' => $opt['option_text'] ?? '',
                        'option_image' => null,
                        'is_correct' => $opt['is_correct'] ?? false,
                        'sort_order' => $index + 1,
                    ]);
                }
            }

            if ($request->has('matches')) {
                $question->matchPairs()->delete();
                foreach (json_decode($request->matches, true) ?? [] as $index => $pair) {
                    $question->matchPairs()->create([
                        'left_value' => $pair['left'] ?? '',
                        'right_value' => $pair['right'] ?? '',
                        'sort_order' => $index + 1,
                    ]);
                }
            }

            return response()->json([
                'message' => 'Question updated successfully',
                'data' => $question->fresh()->load(['grade', 'stream', 'subject', 'lesson', 'type', 'options', 'images', 'matchPairs']),
            ]);
        });
    }

    public function destroy($id)
    {
        $question = Question::findOrFail($id);

        if ($response = $this->ensureQuestionAccess($question)) {
            return $response;
        }

        $question->delete();

        return response()->json(['message' => 'Question deleted successfully']);
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file'],
        ]);

        $extension = strtolower($request->file('file')->getClientOriginalExtension());

        if (!in_array($extension, ['xlsx', 'xls', 'csv'])) {
            return response()->json([
                'message' => 'Only Excel or CSV files are allowed.',
                'errors' => [
                    'file' => ['Only xlsx, xls, or csv files are allowed.'],
                ],
            ], 422);
        }

        $import = new QuestionImport(auth()->id());

        Excel::import($import, $request->file('file'));

        return response()->json([
            'message' => 'Question import completed.',
            'created' => $import->created,
            'skipped' => $import->skipped,
            'errors' => $import->errors,
        ]);
    }

    public function downloadTemplate()
    {
        return Excel::download(
            new QuestionImportTemplateExport(),
            'question_import_template.xlsx'
        );
    }
}
