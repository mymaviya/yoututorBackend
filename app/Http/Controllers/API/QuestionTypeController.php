<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\QuestionTypeAssignment;
use App\Models\QuestionTypeMaster;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class QuestionTypeController extends Controller
{
    public function index(Request $request)
    {
        $query = QuestionTypeAssignment::with(['questionType', 'grade', 'stream', 'subject'])->latest();

        if ($request->filled('grade_id')) {
            $query->where('grade_id', $request->grade_id);
        }

        if ($request->filled('stream_id')) {
            $query->where('stream_id', $request->stream_id);
        }

        if ($request->filled('subject_id')) {
            $query->where('subject_id', $request->subject_id);
        }

        if ($request->boolean('active_only')) {
            $query->where('is_active', true)->whereHas('questionType', fn ($q) => $q->where('is_active', true));
        }

        return response()->json($query->get()->map(function ($assignment) {
            return [
                'id' => $assignment->id,
                'question_type_master_id' => $assignment->question_type_master_id,
                'name' => $assignment->questionType?->name,
                'slug' => $assignment->questionType?->slug,
                'grade_id' => $assignment->grade_id,
                'stream_id' => $assignment->stream_id,
                'subject_id' => $assignment->subject_id,
                'grade' => $assignment->grade,
                'stream' => $assignment->stream,
                'subject' => $assignment->subject,
                'is_active' => $assignment->is_active && (bool) $assignment->questionType?->is_active,
            ];
        }));
    }

    public function masters()
    {
        return response()->json(
            QuestionTypeMaster::where('is_active', true)->orderBy('name')->get()
        );
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'grade_id' => 'nullable|exists:grades,id',
            'stream_id' => 'nullable|exists:streams,id',
            'subject_id' => 'nullable|exists:subjects,id',
            'question_type_master_id' => 'nullable|exists:question_type_masters,id',
            'name' => 'required_without:question_type_master_id|string|max:255',
            'is_active' => 'boolean',
        ]);

        return DB::transaction(function () use ($data) {
            $masterId = $data['question_type_master_id'] ?? null;

            if (!$masterId) {
                $slug = Str::slug($data['name'], '_');
                $master = QuestionTypeMaster::firstOrCreate(
                    ['slug' => $slug],
                    [
                        'name' => $data['name'],
                        'has_options' => in_array($slug, ['mcq', 'multiple_mcq']),
                        'has_match_pairs' => $slug === 'match_column',
                        'is_active' => true,
                    ]
                );
                $masterId = $master->id;
            }

            $assignment = QuestionTypeAssignment::firstOrCreate([
                'question_type_master_id' => $masterId,
                'grade_id' => $data['grade_id'] ?? null,
                'stream_id' => $data['stream_id'] ?? null,
                'subject_id' => $data['subject_id'] ?? null,
            ], ['is_active' => $data['is_active'] ?? true]);

            return response()->json([
                'message' => 'Question type assigned successfully',
                'data' => $assignment->load(['questionType', 'grade', 'stream', 'subject']),
            ], 201);
        });
    }

    public function update(Request $request, $id)
    {
        $assignment = QuestionTypeAssignment::findOrFail($id);

        $data = $request->validate([
            'grade_id' => 'nullable|exists:grades,id',
            'stream_id' => 'nullable|exists:streams,id',
            'subject_id' => 'nullable|exists:subjects,id',
            'question_type_master_id' => 'required|exists:question_type_masters,id',
            'is_active' => 'boolean',
        ]);

        $assignment->update($data);

        return response()->json([
            'message' => 'Question type assignment updated successfully',
            'data' => $assignment->load(['questionType', 'grade', 'stream', 'subject']),
        ]);
    }

    public function destroy($id)
    {
        QuestionTypeAssignment::findOrFail($id)->delete();

        return response()->json(['message' => 'Question type assignment deleted successfully']);
    }

    public function status($id)
    {
        $assignment = QuestionTypeAssignment::findOrFail($id);
        $assignment->update(['is_active' => !$assignment->is_active]);

        return response()->json([
            'message' => 'Status updated successfully',
            'data' => $assignment->load(['questionType', 'grade', 'stream', 'subject']),
        ]);
    }

    public function import(Request $request)
    {
        return response()->json(['message' => 'Question type import needs V2 template update.'], 501);
    }

    public function downloadTemplate()
    {
        return response()->json(['message' => 'Question type template needs V2 update.'], 501);
    }
}
