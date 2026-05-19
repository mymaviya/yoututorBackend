<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Imports\QuestionTypesImport;
use App\Exports\QuestionTypesTemplateExport;
use Maatwebsite\Excel\Facades\Excel;
use App\Models\QuestionType;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class QuestionTypeController extends Controller
{
    public function index(Request $request)
    {
        $query = QuestionType::with(['grade', 'subject'])->latest();

        if ($request->filled('grade_id')) {
            $query->where('grade_id', $request->grade_id);
        }

        if ($request->filled('subject_id')) {
            $query->where('subject_id', $request->subject_id);
        }

        if ($request->filled('active_only')) {
            $query->where('is_active', true);
        }

        return response()->json($query->get());
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv',
        ]);

        $import = new QuestionTypesImport();

        Excel::import($import, $request->file('file'));

        return response()->json([
            'message' => 'Question types import completed',
            'imported' => $import->imported,
            'skipped' => $import->skipped,
            'errors' => $import->errors,
        ]);
    }

    public function downloadTemplate()
    {
        return Excel::download(
            new QuestionTypesTemplateExport,
            'question-types-template.xlsx'
        );
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'grade_id' => 'required|exists:grades,id',
            'subject_id' => 'required|exists:subjects,id',
            'name' => 'required|string|max:255',
            'is_active' => 'boolean',
        ]);

        $exists = QuestionType::where('grade_id', $data['grade_id'])
            ->where('subject_id', $data['subject_id'])
            ->whereRaw('LOWER(name) = ?', [strtolower($data['name'])])
            ->exists();

        if ($exists) {
            return response()->json([
                'message' => 'This question type already exists for the selected grade and subject.',
                'errors' => [
                    'name' => ['This question type already exists for the selected grade and subject.']
                ]
            ], 422);
        }

        $questionType = QuestionType::create([
            'grade_id' => $data['grade_id'],
            'subject_id' => $data['subject_id'],
            'name' => $data['name'],
            'slug' => Str::slug($data['name'], '_'),
            'is_active' => $data['is_active'] ?? true,
        ]);

        return response()->json([
            'message' => 'Question type created successfully',
            'data' => $questionType->load(['grade', 'subject']),
        ], 201);
    }

    public function update(Request $request, QuestionType $questionType)
    {
        $data = $request->validate([
            'grade_id' => 'required|exists:grades,id',
            'subject_id' => 'required|exists:subjects,id',
            'name' => 'required|string|max:255',
            'is_active' => 'boolean',
        ]);

        $exists = QuestionType::where('grade_id', $data['grade_id'])
            ->where('subject_id', $data['subject_id'])
            ->whereRaw('LOWER(name) = ?', [strtolower($data['name'])])
            ->where('id', '!=', $questionType->id)
            ->exists();

        if ($exists) {
            return response()->json([
                'message' => 'This question type already exists for the selected grade and subject.',
                'errors' => [
                    'name' => ['This question type already exists for the selected grade and subject.']
                ]
            ], 422);
        }

        $questionType->update([
            'grade_id' => $data['grade_id'],
            'subject_id' => $data['subject_id'],
            'name' => $data['name'],
            'slug' => Str::slug($data['name'], '_'),
            'is_active' => $data['is_active'] ?? true,
        ]);

        return response()->json([
            'message' => 'Question type updated successfully',
            'data' => $questionType->load(['grade', 'subject']),
        ]);
    }

    public function destroy(QuestionType $questionType)
    {
        $questionType->delete();

        return response()->json([
            'message' => 'Question type deleted successfully',
        ]);
    }

    public function status(QuestionType $questionType)
    {
        $questionType->update([
            'is_active' => !$questionType->is_active,
        ]);

        return response()->json([
            'message' => 'Status updated successfully',
            'data' => $questionType,
        ]);
    }
}
