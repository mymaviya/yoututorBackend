<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SchoolBell;
use App\Models\TeacherAvailability;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TeacherAvailabilityController extends Controller
{
    public function index(Request $request)
    {
        $query = TeacherAvailability::with(['teacher', 'bell']);

        if ($request->filled('teacher_id')) {
            $query->where('teacher_id', $request->teacher_id);
        }

        if ($request->filled('weekday')) {
            $query->where('weekday', $request->weekday);
        }

        return response()->json([
            'success' => true,
            'data' => $query->latest()->paginate($request->get('per_page', 20)),
        ]);
    }

    public function bulkEditorData()
    {
        $subscriptionId = auth()->user()->subscription_id;

        $teachers = User::teachers()
            ->where('subscription_id', $subscriptionId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        $bells = SchoolBell::where('is_active', true)
            ->where('is_teaching_period', true)
            ->orderBy('sort_order')
            ->get(['id', 'title', 'period_number', 'start_time', 'end_time']);

        $availability = TeacherAvailability::where('subscription_id', $subscriptionId)
            ->get()
            ->groupBy('teacher_id');

        return response()->json([
            'success' => true,
            'data' => [
                'teachers' => $teachers,
                'bells' => $bells,
                'availability' => $availability,
                'weekdays' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'],
            ],
        ]);
    }

    public function bulkSave(Request $request)
    {
        $data = $request->validate([
            'items' => 'required|array',
            'items.*.teacher_id' => 'required|exists:users,id',
            'items.*.weekday' => 'required|string',
            'items.*.school_bell_id' => 'required|exists:school_bells,id',
            'items.*.status' => 'required|in:available,busy,leave,meeting,blocked',
            'items.*.reason' => 'nullable|string|max:255',
        ]);

        $subscriptionId = auth()->user()->subscription_id;

        DB::transaction(function () use ($data, $subscriptionId) {
            foreach ($data['items'] as $item) {
                TeacherAvailability::updateOrCreate(
                    [
                        'subscription_id' => $subscriptionId,
                        'teacher_id' => $item['teacher_id'],
                        'weekday' => $item['weekday'],
                        'school_bell_id' => $item['school_bell_id'],
                    ],
                    [
                        'status' => $item['status'],
                        'reason' => $item['reason'] ?? null,
                        'is_active' => true,
                    ]
                );
            }
        });

        return response()->json([
            'success' => true,
            'message' => 'Teacher availability saved successfully.',
        ]);
    }

    public function destroy(TeacherAvailability $teacherAvailability)
    {
        $teacherAvailability->delete();

        return response()->json([
            'success' => true,
            'message' => 'Teacher availability deleted successfully.',
        ]);
    }
}