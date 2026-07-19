<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\WeeklyTimetable;
use App\Services\AcademicPlanning\TimetableLifecycleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TimetableLifecycleController extends Controller
{
    public function __construct(
        protected TimetableLifecycleService $service
    ) {}

    public function index(Request $request): JsonResponse
    {
        $subscriptionId = $this->subscriptionId($request);
        $data = $request->validate([
            'status' => ['nullable', Rule::in(WeeklyTimetable::STATUSES)],
            'academic_year_id' => ['nullable', 'integer'],
            'grade_id' => ['nullable', 'integer'],
            'section_id' => ['nullable', 'integer'],
            'stream_id' => ['nullable', 'integer'],
            'search' => ['nullable', 'string', 'max:150'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = WeeklyTimetable::query()
            ->forSubscription($subscriptionId)
            ->with(['template', 'academicYear', 'grade', 'section', 'stream', 'publisher:id,name,email'])
            ->withCount('entries')
            ->when($data['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($data['academic_year_id'] ?? null, fn ($query, $id) => $query->where('academic_year_id', $id))
            ->when($data['grade_id'] ?? null, fn ($query, $id) => $query->where('grade_id', $id))
            ->when(array_key_exists('section_id', $data), fn ($query) => $query->where('section_id', $data['section_id']))
            ->when(array_key_exists('stream_id', $data), fn ($query) => $query->where('stream_id', $data['stream_id']))
            ->when($data['search'] ?? null, fn ($query, $search) => $query->where('name', 'like', '%' . $search . '%'))
            ->orderByDesc('effective_from')
            ->orderByDesc('version')
            ->orderByDesc('id');

        return response()->json([
            'success' => true,
            'data' => $query->paginate($data['per_page'] ?? 20),
        ]);
    }

    public function publish(Request $request, WeeklyTimetable $weeklyTimetable): JsonResponse
    {
        $this->ensureOwned($request, $weeklyTimetable);

        return response()->json([
            'success' => true,
            'message' => 'Timetable published successfully.',
            'data' => $this->service->publish($weeklyTimetable, (int) $request->user()->id),
        ]);
    }

    public function archive(Request $request, WeeklyTimetable $weeklyTimetable): JsonResponse
    {
        $this->ensureOwned($request, $weeklyTimetable);

        return response()->json([
            'success' => true,
            'message' => 'Timetable archived successfully.',
            'data' => $this->service->archive($weeklyTimetable),
        ]);
    }

    public function restore(Request $request, WeeklyTimetable $weeklyTimetable): JsonResponse
    {
        $this->ensureOwned($request, $weeklyTimetable);

        return response()->json([
            'success' => true,
            'message' => 'Timetable restored to draft successfully.',
            'data' => $this->service->restoreToDraft($weeklyTimetable),
        ]);
    }

    public function createVersion(Request $request, WeeklyTimetable $weeklyTimetable): JsonResponse
    {
        $this->ensureOwned($request, $weeklyTimetable);
        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:150'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'New timetable version created successfully.',
            'data' => $this->service->createVersion($weeklyTimetable, $data['name'] ?? null),
        ], 201);
    }

    private function ensureOwned(Request $request, WeeklyTimetable $timetable): void
    {
        abort_unless(
            (int) $timetable->subscription_id === $this->subscriptionId($request),
            404
        );
    }

    private function subscriptionId(Request $request): int
    {
        $subscriptionId = $request->user()?->subscription_id
            ?? $request->user()?->subscription?->id;

        abort_if(
            ! is_numeric($subscriptionId) || (int) $subscriptionId <= 0,
            403,
            'A valid subscription is required.'
        );

        return (int) $subscriptionId;
    }
}
