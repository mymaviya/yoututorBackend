<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Grade;
use Illuminate\Validation\Rule;

class GradeController extends Controller
{
    public function index(Request $request)
    {
        $query = Grade::query();

        if ($request->search) {
            $query->where('name', 'like', "%{$request->search}%");
        }

        $data = $query
            ->orderByRaw("CAST(REGEXP_SUBSTR(name, '[0-9]+') AS UNSIGNED)")
            ->orderBy('name')
            ->get();

        return response()->json($data);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                'unique:grades,name',
            ],
            'is_active' => ['nullable', 'boolean'],
        ]);

        return Grade::create([
            'name' => $validated['name'],
            'is_active' => $validated['is_active'] ?? true,
        ]);
    }

    public function show(string $id)
    {
        return Grade::findOrFail($id);
    }

    public function update(Request $request, string $id)
    {
        $grade = Grade::findOrFail($id);

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('grades', 'name')->ignore($grade->id),
            ],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $grade->update([
            'name' => $validated['name'],
            'is_active' => $validated['is_active'] ?? $grade->is_active,
        ]);

        return $grade;
    }

    public function status(string $id)
    {
        $grade = Grade::findOrFail($id);

        $grade->update([
            'is_active' => !$grade->is_active,
        ]);

        return $grade;
    }

    public function destroy(string $id)
    {
        $grade = Grade::findOrFail($id);
        $grade->delete();

        return response()->json(null, 204);
    }
}