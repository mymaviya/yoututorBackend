<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Subject;

class SubjectController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Subject::with('grade');

        // 🔍 Filter by grade
        if ($request->grade_id) {
            $query->where('grade_id', $request->grade_id);
        }

        return $query->get();
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required',
            'grade_id' => 'required|exists:grades,id'
        ]);

        return Subject::create($request->all());
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $subject = Subject::findOrFail($id);

        $request->validate([
            'name' => 'required',
            'grade_id' => 'required|exists:grades,id'
        ]);

        $subject->update($request->all());

        return $subject;
    }


    public function status(string $id)
    {
        $subject = Subject::findOrFail($id);
        $subject->update(['is_active' => !$subject->is_active]);
        return $subject;
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        Subject::findOrFail($id)->delete();

        return response()->json(['message' => 'Deleted']);
    }
}
