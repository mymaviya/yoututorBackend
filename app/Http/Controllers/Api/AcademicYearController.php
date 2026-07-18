<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use Illuminate\Http\Request;

class AcademicYearController extends Controller
{
    public function index(Request $request)
    {
        $query = AcademicYear::query();

        if ($request->filled('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        return response()->json([
            'success' => true,
            'data' => $query
                ->orderByDesc('is_current')
                ->orderByDesc('start_date')
                ->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'is_current' => 'boolean',
            'is_active' => 'boolean',
        ]);

        $data['subscription_id'] = auth()->user()->subscription_id;

        if (! empty($data['is_current'])) {
            AcademicYear::where('subscription_id', $data['subscription_id'])
                ->update(['is_current' => false]);
        }

        $academicYear = AcademicYear::create($data);

        return response()->json([
            'success' => true,
            'message' => 'Academic year created successfully.',
            'data' => $academicYear,
        ]);
    }

    public function show(AcademicYear $academicYear)
    {
        return response()->json([
            'success' => true,
            'data' => $academicYear,
        ]);
    }

    public function update(Request $request, AcademicYear $academicYear)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'is_current' => 'boolean',
            'is_active' => 'boolean',
        ]);

        if (! empty($data['is_current'])) {
            AcademicYear::where('subscription_id', $academicYear->subscription_id)
                ->where('id', '!=', $academicYear->id)
                ->update(['is_current' => false]);
        }

        $academicYear->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Academic year updated successfully.',
            'data' => $academicYear,
        ]);
    }

    public function destroy(AcademicYear $academicYear)
    {
        $academicYear->delete();

        return response()->json([
            'success' => true,
            'message' => 'Academic year deleted successfully.',
        ]);
    }
}