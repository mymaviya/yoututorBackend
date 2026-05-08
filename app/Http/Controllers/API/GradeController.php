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
        $data = Grade::latest()->get();
        return response()->json($data);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|unique:grades,name'
        ]);

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
            'name' => 'required|unique:grades,name,' . $grade->id
        ]);

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
