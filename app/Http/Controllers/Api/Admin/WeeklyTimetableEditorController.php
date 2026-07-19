<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\TimetableEntry;
use App\Models\WeeklyTimetable;
use App\Services\AcademicPlanning\WeeklyTimetableEditorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class WeeklyTimetableEditorController extends Controller
{
    public function __construct(
        protected WeeklyTimetableEditorService $service
    ) {}

    public function grid(Request $request, WeeklyTimetable $weeklyTimetable): JsonResponse
    {
        $this->ensureOwned($request, $weeklyTimetable);

        return response()->json([
            'success' => true,
            'data' => $this->service->grid($weeklyTimetable),
        ]);
    }

    public function store(
        Request $request,
        WeeklyTimetable $weeklyTimetable
    ): JsonResponse {
        $this->ensureOwned($request, $weeklyTimetable);

        return response()->json([
            'success' => true,
            'message' => 'Timetable entry created successfully.',
            'data' => $this->service->create(
                $weeklyTimetable,
                $this->validatedEntry($request, $weeklyTimetable)
            ),
        ], 201);
    }

    public function update(
        Request $request,
        WeeklyTimetable $weeklyTimetable,
        TimetableEntry $timetableEntry
    ): JsonResponse {
        $this->ensureOwned($request, $weeklyTimetable);
        $this->ensureEntryBelongsToTimetable($timetableEntry, $weeklyTimetable);

        return response()->json([
            'success' => true,
            'message' => 'Timetable entry updated successfully.',
            'data' => $this->service->update(
                $timetableEntry,
                $this->validatedEntry($request, $weeklyTimetable, true)
            ),
        ]);
    }

    public function destroy(
        Request $request,
        WeeklyTimetable $weeklyTimetable,
        TimetableEntry $timetableEntry
    ): JsonResponse {
        $this->ensureOwned($request, $weeklyTimetable);
        $this->ensureEntryBelongsToTimetable($timetableEntry, $weeklyTimetable);
        $data = $request->validate([
            'force_locked' => ['nullable', 'boolean'],
        ]);

        $this->service->delete(
            $timetableEntry,
            (bool) ($data['force_locked'] ?? false)
        );

        return response()->json([
            'success' => true,
            'message' => 'Timetable entry deleted successfully.',
        ]);
    }

    public function replaceGrid(
        Request $request,
        WeeklyTimetable $weeklyTimetable
    ): JsonResponse {
        $this->ensureOwned($request, $weeklyTimetable);
        $subscriptionId = (int) $weeklyTimetable->subscription_id;

        $data = $request->validate([
            'preserve_locked' => ['nullable', 'boolean'],
            'entries' => ['required', 'array', 'max:500'],
            'entries.*.id' => ['nullable', 'integer'],
            'entries.*.weekday' => ['required', 'integer', 'min:1', 'max:7'],
            'entries.*.school_bell_id' => [
                'required', 'integer',
                Rule::exists('school_bells', 'id')->where(
                    fn ($query) => $query->where('subscription_id', $subscriptionId)
                ),
            ],
            'entries.*.teacher_id' => [
                'nullable', 'integer',
                Rule::exists('users', 'id')->where(
                    fn ($query) => $query->where('subscription_id', $subscriptionId)
                ),
            ],
            'entries.*.subject_id' => [
                'nullable', 'integer',
                Rule::exists('subjects', 'id')->where(
                    fn ($query) => $query->where('subscription_id', $subscriptionId)
                ),
            ],
            'entries.*.lesson_id' => [
                'nullable', 'integer',
                Rule::exists('lessons', 'id')->where(
                    fn ($query) => $query->where('subscription_id', $subscriptionId)
                ),
            ],
            'entries.*.parallel_group_id' => [
                'nullable', 'integer',
                Rule::exists('parallel_groups', 'id')->where(
                    fn ($query) => $query->where('subscription_id', $subscriptionId)
                ),
            ],
            'entries.*.student_group_name' => ['nullable', 'string', 'max:150'],
            'entries.*.room_id' => [
                'nullable', 'integer',
                Rule::exists('timetable_rooms', 'id')->where(
                    fn ($query) => $query->where('subscription_id', $subscriptionId)
                ),
            ],
            'entries.*.room_no' => ['nullable', 'string', 'max:100'],
            'entries.*.is_parallel' => ['nullable', 'boolean'],
            'entries.*.is_substitution' => ['nullable', 'boolean'],
            'entries.*.substitute_teacher_id' => [
                'nullable', 'integer',
                Rule::exists('users', 'id')->where(
                    fn ($query) => $query->where('subscription_id', $subscriptionId)
                ),
            ],
            'entries.*.remarks' => ['nullable', 'string', 'max:1000'],
            'entries.*.is_locked' => ['nullable', 'boolean'],
            'entries.*.is_active' => ['nullable', 'boolean'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Timetable grid saved successfully.',
            'data' => $this->service->replaceGrid(
                $weeklyTimetable,
                $data['entries'],
                (bool) ($data['preserve_locked'] ?? true)
            ),
        ]);
    }

    private function validatedEntry(
        Request $request,
        WeeklyTimetable $timetable,
        bool $partial = false
    ): array {
        $subscriptionId = (int) $timetable->subscription_id;
        $required = $partial ? 'sometimes' : 'required';

        return $request->validate([
            'weekday' => [$required, 'integer', 'min:1', 'max:7'],
            'school_bell_id' => [
                $required, 'integer',
                Rule::exists('school_bells', 'id')->where(
                    fn ($query) => $query->where('subscription_id', $subscriptionId)
                ),
            ],
            'teacher_id' => [
                'nullable', 'integer',
                Rule::exists('users', 'id')->where(
                    fn ($query) => $query->where('subscription_id', $subscriptionId)
                ),
            ],
            'subject_id' => [
                'nullable', 'integer',
                Rule::exists('subjects', 'id')->where(
                    fn ($query) => $query->where('subscription_id', $subscriptionId)
                ),
            ],
            'lesson_id' => [
                'nullable', 'integer',
                Rule::exists('lessons', 'id')->where(
                    fn ($query) => $query->where('subscription_id', $subscriptionId)
                ),
            ],
            'parallel_group_id' => [
                'nullable', 'integer',
                Rule::exists('parallel_groups', 'id')->where(
                    fn ($query) => $query->where('subscription_id', $subscriptionId)
                ),
            ],
            'student_group_name' => ['nullable', 'string', 'max:150'],
            'room_id' => [
                'nullable', 'integer',
                Rule::exists('timetable_rooms', 'id')->where(
                    fn ($query) => $query->where('subscription_id', $subscriptionId)
                ),
            ],
            'room_no' => ['nullable', 'string', 'max:100'],
            'is_parallel' => ['nullable', 'boolean'],
            'is_substitution' => ['nullable', 'boolean'],
            'substitute_teacher_id' => [
                'nullable', 'integer',
                Rule::exists('users', 'id')->where(
                    fn ($query) => $query->where('subscription_id', $subscriptionId)
                ),
                'required_if:is_substitution,true',
            ],
            'remarks' => ['nullable', 'string', 'max:1000'],
            'is_locked' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ]);
    }

    private function ensureOwned(Request $request, WeeklyTimetable $timetable): void
    {
        abort_unless(
            (int) $timetable->subscription_id === $this->subscriptionId($request),
            404
        );
    }

    private function ensureEntryBelongsToTimetable(
        TimetableEntry $entry,
        WeeklyTimetable $timetable
    ): void {
        abort_unless(
            (int) $entry->weekly_timetable_id === (int) $timetable->id,
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
