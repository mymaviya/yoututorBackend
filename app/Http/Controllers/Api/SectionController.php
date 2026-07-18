<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Section;
use Illuminate\Http\Request;

class SectionController extends Controller
{
    /**
     * Return sections for dropdowns.
     */
    public function index(Request $request)
    {
        $query = Section::query()
            ->with(['grade', 'stream'])
            ->where('is_active', true);

        if ($request->filled('grade_id')) {
            $query->where('grade_id', $request->grade_id);
        }

        if ($request->filled('stream_id')) {
            $query->where('stream_id', $request->stream_id);
        }

        return response()->json([
            'success' => true,
            'data' => $query
                ->orderBy('name')
                ->get([
                    'id',
                    'grade_id',
                    'stream_id',
                    'name',
                    'display_name',
                ]),
        ]);
    }
}