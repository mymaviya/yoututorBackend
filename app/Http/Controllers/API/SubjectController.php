<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Subject;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SubjectController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Subject::with(['grade', 'stream']);

        // Filter by grade
        if ($request->filled('grade_id')) {
            $query->where('grade_id', $request->grade_id);
        }

        // Filter by stream
        // For Class 1-10 subjects, stream_id may be null.
        // For Class 11-12 subjects, stream_id should be selected.
        if ($request->filled('stream_id')) {
            $query->where('stream_id', $request->stream_id);
        }

        return $query
            ->orderBy('grade_id')
            ->orderBy('stream_id')
            ->orderBy('name')
            ->get();
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('subjects')->where(function ($query) use ($request) {
                    return $query
                        ->where('grade_id', $request->grade_id)
                        ->where('stream_id', $request->stream_id);
                }),
            ],
            'grade_id' => ['required', 'exists:grades,id'],
            'stream_id' => ['nullable', 'exists:streams,id'],
            'is_active' => ['nullable', 'boolean'],
        ], [
            'name.unique' => 'This subject already exists for the selected class and stream.',
            'grade_id.required' => 'Class is required.',
            'grade_id.exists' => 'Selected class is invalid.',
            'stream_id.exists' => 'Selected stream is invalid.',
        ]);

        $validated['is_active'] = $request->boolean('is_active', true);

        $subject = Subject::create($validated);

        return response()->json(
            $subject->load(['grade', 'stream']),
            201
        );
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        return Subject::with(['grade', 'stream'])->findOrFail($id);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $subject = Subject::findOrFail($id);

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('subjects')->ignore($subject->id)->where(function ($query) use ($request) {
                    return $query
                        ->where('grade_id', $request->grade_id)
                        ->where('stream_id', $request->stream_id);
                }),
            ],
            'grade_id' => ['required', 'exists:grades,id'],
            'stream_id' => ['nullable', 'exists:streams,id'],
            'is_active' => ['nullable', 'boolean'],
        ], [
            'name.unique' => 'This subject already exists for the selected class and stream.',
            'grade_id.required' => 'Class is required.',
            'grade_id.exists' => 'Selected class is invalid.',
            'stream_id.exists' => 'Selected stream is invalid.',
        ]);

        if ($request->has('is_active')) {
            $validated['is_active'] = $request->boolean('is_active');
        }

        $subject->update($validated);

        return response()->json(
            $subject->load(['grade', 'stream'])
        );
    }

    /**
     * Toggle active/inactive status.
     */
    public function status(string $id)
    {
        $subject = Subject::findOrFail($id);

        $subject->update([
            'is_active' => !$subject->is_active,
        ]);

        return response()->json(
            $subject->load(['grade', 'stream'])
        );
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        Subject::findOrFail($id)->delete();

        return response()->json([
            'message' => 'Deleted',
        ]);
    }
}
