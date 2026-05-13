<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Lesson;

class LessonController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Lesson::with(['subject', 'subject.grade']);

        // 🔍 Filter by subject
        if ($request->subject_id) {
            $query->where('subject_id', $request->subject_id);
        }

        return $query->get();
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'grade_id' => 'required|exists:grades,id',
            'subject_id' => 'required|exists:subjects,id',
            'title' => 'required|string|max:255',
        ]);

        $exists = Lesson::where('subject_id', $data['subject_id'])
            ->whereRaw('LOWER(title) = ?', [strtolower($data['title'])])
            ->exists();

        if ($exists) {
            return response()->json([
                'message' => 'This lesson already exists for the selected subject.',
                'errors' => [
                    'title' => ['This lesson already exists for the selected subject.']
                ]
            ], 422);
        }

        $lesson = Lesson::create($data);

        return $lesson;
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        return Lesson::with(['subject', 'subject.grade'])->findOrFail($id);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $lesson = Lesson::findOrFail($id);

        $data = $request->validate([
            'grade_id' => 'required|exists:grades,id',
            'subject_id' => 'required|exists:subjects,id',
            'title' => 'required|string|max:255',
        ]);

        $exists = Lesson::where('subject_id', $data['subject_id'])
            ->whereRaw('LOWER(title) = ?', [strtolower($data['title'])])
            ->where('id', '!=', $lesson->id)
            ->exists();

        if ($exists) {
            return response()->json([
                'message' => 'This lesson already exists for the selected subject.',
                'errors' => [
                    'title' => ['This lesson already exists for the selected subject.']
                ]
            ], 422);
        }

        $lesson->update($data);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        Lesson::findOrFail($id)->delete();

        return response()->json(['message' => 'Deleted']);
    }
}
