<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Subject;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SubjectController extends Controller
{
    public function index(Request $request)
    {
        $query = Subject::with(['grade', 'stream']);

        if ($request->filled('grade_id')) {
            $query->where('grade_id', $request->grade_id);
        }

        if ($request->filled('stream_id')) {
            $streamId = $request->stream_id;

            $query->where(function ($q) use ($streamId) {
                $q->whereNull('stream_id')
                    ->orWhere('stream_id', $streamId);
            });
        }

        return $query
            ->orderBy('grade_id')
            ->orderByRaw('stream_id IS NOT NULL')
            ->orderBy('stream_id')
            ->orderBy('name')
            ->get();
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'grade_id' => ['required', 'exists:grades,id'],
            'stream_id' => ['nullable', 'exists:streams,id'],
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('subjects')->where(function ($query) use ($request) {
                    return $query
                        ->where('grade_id', $request->grade_id)
                        ->where(function ($q) use ($request) {
                            if ($request->filled('stream_id')) {
                                $q->where('stream_id', $request->stream_id);
                            } else {
                                $q->whereNull('stream_id');
                            }
                        });
                }),
            ],
            'is_active' => ['nullable', 'boolean'],
        ], [
            'name.unique' => 'This subject already exists for the selected class and stream.',
            'grade_id.required' => 'Class is required.',
            'grade_id.exists' => 'Selected class is invalid.',
            'stream_id.exists' => 'Selected stream is invalid.',
        ]);

        $validated['stream_id'] = $validated['stream_id'] ?? null;
        $validated['is_active'] = $request->boolean('is_active', true);

        $subject = Subject::create($validated);

        return response()->json(
            $subject->load(['grade', 'stream']),
            201
        );
    }

    public function show(string $id)
    {
        return Subject::with(['grade', 'stream'])->findOrFail($id);
    }

    public function update(Request $request, string $id)
    {
        $subject = Subject::findOrFail($id);

        $validated = $request->validate([
            'grade_id' => ['required', 'exists:grades,id'],
            'stream_id' => ['nullable', 'exists:streams,id'],
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('subjects')->ignore($subject->id)->where(function ($query) use ($request) {
                    return $query
                        ->where('grade_id', $request->grade_id)
                        ->where(function ($q) use ($request) {
                            if ($request->filled('stream_id')) {
                                $q->where('stream_id', $request->stream_id);
                            } else {
                                $q->whereNull('stream_id');
                            }
                        });
                }),
            ],
            'is_active' => ['nullable', 'boolean'],
        ], [
            'name.unique' => 'This subject already exists for the selected class and stream.',
            'grade_id.required' => 'Class is required.',
            'grade_id.exists' => 'Selected class is invalid.',
            'stream_id.exists' => 'Selected stream is invalid.',
        ]);

        $validated['stream_id'] = $validated['stream_id'] ?? null;

        if ($request->has('is_active')) {
            $validated['is_active'] = $request->boolean('is_active');
        }

        $subject->update($validated);

        return response()->json(
            $subject->load(['grade', 'stream'])
        );
    }

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

    public function destroy(string $id)
    {
        Subject::findOrFail($id)->delete();

        return response()->json([
            'message' => 'Deleted',
        ]);
    }
}