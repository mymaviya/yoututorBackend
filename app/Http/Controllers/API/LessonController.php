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
        $request->validate([
            'title' => 'required',
            'subject_id' => 'required|exists:subjects,id'
        ]);

        return Lesson::create($request->all());
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

        $request->validate([
            'title' => 'required',
            'subject_id' => 'required|exists:subjects,id'
        ]);

        $lesson->update($request->all());

        return $lesson;
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
