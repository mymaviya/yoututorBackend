<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ExamName;
use Illuminate\Http\Request;

class ExamNameController extends Controller
{
    public function index()
    {
        return ExamName::latest()->get();
    }

    public function show(ExamName $examName)
    {
        return response()->json($examName);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255|unique:exam_names,name',
            'tentative_date' => 'required|nullable|date',
            'is_active' => 'boolean',
        ]);

        $examName = ExamName::create([
            'name' => $data['name'],
            'tentative_date' => $data['tentative_date'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ]);

        return response()->json([
            'message' => 'Exam name created successfully',
            'data' => $examName,
        ], 201);
    }

    public function update(Request $request, ExamName $examName)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255|unique:exam_names,name,' . $examName->id,
            'tentative_date' => 'required|nullable|date',
            'is_active' => 'boolean',
        ]);

        $examName->update([
            'name' => $data['name'],
            'tentative_date' => $data['tentative_date'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ]);

        return response()->json([
            'message' => 'Exam name updated successfully',
            'data' => $examName,
        ]);
    }

    public function destroy(ExamName $examName)
    {
        $examName->delete();

        return response()->json([
            'message' => 'Exam name deleted successfully',
        ]);
    }

    public function status(ExamName $examName)
    {
        $examName->update([
            'is_active' => !$examName->is_active,
        ]);

        return response()->json([
            'message' => 'Status updated successfully',
            'data' => $examName,
        ]);
    }
}
