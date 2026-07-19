<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\TimetableEntry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class TimetableEntryLockController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $subscriptionId = $this->subscriptionId($request);
        $data = $request->validate([
            'weekly_timetable_id' => [
                'nullable',
                'integer',
                Rule::exists('weekly_timetables', 'id')->where('subscription_id', $subscriptionId),
            ],
            'is_locked' => ['nullable', 'boolean'],
            'weekday' => ['nullable', 'integer', 'min:1', 'max:7'],
            'teacher_id' => ['nullable', 'integer'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = TimetableEntry::query()
            ->forSubscription($subscriptionId)
            ->with([
                'weeklyTimetable:id,name,grade_id,section_id,stream_id',
                'bell:id,name,period_number,start_time,end_time',
                'subject:id,name',
                'teacher:id,name',
                'room:id,name,code',
                'parallelGroup:id,name',
            ])
            ->when(
                $data['weekly_timetable_id'] ?? null,
                fn ($query, $id) => $query->where('weekly_timetable_id', $id)
            )
            ->when(
                array_key_exists('is_locked', $data),
                fn ($query) => $query->where('is_locked', $data['is_locked'])
            )
            ->when($data['weekday'] ?? null, fn ($query, $weekday) => $query->where('weekday', $weekday))
            ->when($data['teacher_id'] ?? null, fn ($query, $teacherId) => $query->forTeacher($teacherId))
            ->orderBy('weekly_timetable_id')
            ->orderBy('weekday')
            ->orderBy('school_bell_id')
            ->orderBy('id');

        return response()->json([
            'success' => true,
            'data' => $query->paginate($data['per_page'] ?? 50),
        ]);
    }

    public function lock(Request $request, TimetableEntry $timetableEntry): JsonResponse
    {
        $this->ensureOwned($request, $timetableEntry);
        $timetableEntry->forceFill(['is_locked' => true])->save();

        return response()->json([
            'success' => true,
            'message' => 'Timetable entry locked successfully.',
            'data' => $this->freshEntry($timetableEntry),
        ]);
    }

    public function unlock(Request $request, TimetableEntry $timetableEntry): JsonResponse
    {
        $this->ensureOwned($request, $timetableEntry);
        $timetableEntry->forceFill(['is_locked' => false])->save();

        return response()->json([
            'success' => true,
            'message' => 'Timetable entry unlocked successfully.',
            'data' => $this->freshEntry($timetableEntry),
        ]);
    }

    public function bulkLock(Request $request): JsonResponse
    {
        return $this->bulkSet($request, true);
    }

    public function bulkUnlock(Request $request): JsonResponse
    {
        return $this->bulkSet($request, false);
    }

    private function bulkSet(Request $request, bool $locked): JsonResponse
    {
        $subscriptionId = $this->subscriptionId($request);
        $data = $request->validate([
            'entry_ids' => ['required', 'array', 'min:1', 'max:500'],
            'entry_ids.*' => ['required', 'integer', 'distinct'],
        ]);

        $entryIds = collect($data['entry_ids'])->map(fn ($id) => (int) $id)->values();

        $ownedIds = TimetableEntry::query()
            ->forSubscription($subscriptionId)
            ->whereIn('id', $entryIds)
            ->pluck('id');

        abort_unless($ownedIds->count() === $entryIds->unique()->count(), 404);

        DB::transaction(function () use ($ownedIds, $locked): void {
            TimetableEntry::query()
                ->whereIn('id', $ownedIds)
                ->update([
                    'is_locked' => $locked,
                    'updated_at' => now(),
                ]);
        });

        return response()->json([
            'success' => true,
            'message' => $locked
                ? 'Timetable entries locked successfully.'
                : 'Timetable entries unlocked successfully.',
            'data' => [
                'updated_count' => $ownedIds->count(),
                'is_locked' => $locked,
            ],
        ]);
    }

    private function freshEntry(TimetableEntry $entry): TimetableEntry
    {
        return $entry->fresh([
            'weeklyTimetable:id,name,grade_id,section_id,stream_id',
            'bell:id,name,period_number,start_time,end_time',
            'subject:id,name',
            'teacher:id,name',
            'room:id,name,code',
            'parallelGroup:id,name',
        ]);
    }

    private function ensureOwned(Request $request, TimetableEntry $entry): void
    {
        abort_unless(
            $entry->weeklyTimetable()
                ->where('subscription_id', $this->subscriptionId($request))
                ->exists(),
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
