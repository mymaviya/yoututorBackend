<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Grade;

class GradeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        // $query = Grade::latest()->get();

        // // 🔍 Search
        // if ($request->search) {
        //     $query->where('name', 'like', "%{$request->search}%");
        // }

        // // 🎯 Filter
        // if ($request->class_id) {
        //     $query->where('class_id', $request->class_id);
        // }

        // // 🔃 Sorting
        // if ($request->sortBy) {
        //     $direction = $request->sortDesc === 'true' ? 'desc' : 'asc';
        //     $query->orderBy($request->sortBy, $direction);
        // }

        // // 📄 Pagination
        // $perPage = $request->itemsPerPage ?? 10;

        // return $query->paginate($perPage);
        $data = Grade::orderByRaw("CAST(REGEXP_SUBSTR(name, '[0-9]+') AS UNSIGNED)")->get();
        return response()->json($data);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {

        $request->validate([
            'name' => 'required|string|max:255',
            'stream' => 'nullable|string|max:255',
        ]);

        $exists = Grade::where('name', $request->name)
            ->where('stream', $request->stream)
            ->exists();

        if ($exists) {
            return response()->json([
                'errors' => [
                    'name' => ['This grade and stream combination already exists.']
                ]
            ], 422);
        }

        return Grade::create($request->all());
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        return Grade::findOrFail($id);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $grade = Grade::findOrFail($id);

        $request->validate([
            'name' => 'required|string|max:255',
            'stream' => 'nullable|string|max:255',
        ]);

        $exists = Grade::where('name', $request->name)
            ->where('stream', $request->stream)
            ->exists();

        if ($exists) {
            return response()->json([
                'errors' => [
                    'name' => ['This grade and stream combination already exists.']
                ]
            ], 422);
        }

        $grade->update($request->all());

        return $grade;
    }

    public function status(string $id)
    {
        $grade = Grade::findOrFail($id);
        $grade->update(['is_active' => !$grade->is_active]);
        return $grade;
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $grade = Grade::findOrFail($id);
        $grade->delete();
        return response()->json(null, 204);
    }
}
