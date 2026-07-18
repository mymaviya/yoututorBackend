<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSchoolNoticeRequest;
use App\Http\Requests\UpdateSchoolNoticeRequest;
use App\Models\SchoolNotice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SchoolNoticeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = SchoolNotice::query();

        if ($request->filled('search')) {
            $query->where(
                'title',
                'like',
                '%' . $request->search . '%'
            );
        }

        $notices = $query
            ->latest()
            ->paginate(20);

        return response()->json($notices);
    }

    public function store(
        StoreSchoolNoticeRequest $request
    ): JsonResponse {
        $notice = SchoolNotice::create([
            ...$request->validated(),
            'created_by' => auth()->id(),
        ]);

        return response()->json([
            'message' => 'Notice created successfully.',
            'data' => $notice,
        ]);
    }

    public function show(
        SchoolNotice $schoolNotice
    ): JsonResponse {
        return response()->json($schoolNotice);
    }

    public function update(
        UpdateSchoolNoticeRequest $request,
        SchoolNotice $schoolNotice
    ): JsonResponse {
        $schoolNotice->update(
            $request->validated()
        );

        return response()->json([
            'message' => 'Notice updated successfully.',
            'data' => $schoolNotice,
        ]);
    }

    public function destroy(
        SchoolNotice $schoolNotice
    ): JsonResponse {
        $schoolNotice->delete();

        return response()->json([
            'message' => 'Notice deleted successfully.',
        ]);
    }

    public function toggle(
        SchoolNotice $schoolNotice
    ): JsonResponse {
        $schoolNotice->update([
            'is_active' => ! $schoolNotice->is_active,
        ]);

        return response()->json([
            'message' => 'Status updated.',
            'data' => $schoolNotice,
        ]);
    }
}