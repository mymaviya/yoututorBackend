<?php

namespace App\Services\AcademicPlanning;

use App\Models\ParallelGroup;
use App\Models\SubjectPeriodAllocation;
use App\Models\TimetableEntry;
use App\Models\TimetableRoom;
use App\Models\WeeklyTimetable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AdvancedTimetableGeneratorService
{
    public function __construct(
        protected AutomaticTimetableGeneratorService $baseGenerator
    ) {}

    public function generate(int $subscriptionId, array $data, bool $preview = false): array
    {
        $lockedEntries = $this->lockedEntries($subscriptionId, $data);
        $baseResult = $this->baseGenerator->generate($subscriptionId, $data, true);

        $entries = collect($baseResult['entries'] ?? []);
        $warnings = collect($baseResult['warnings'] ?? []);

        [$entries, $lockWarnings] = $this->preserveLockedEntries($entries, $lockedEntries);
        $warnings = $warnings->merge($lockWarnings);

        [$entries, $parallelWarnings] = $this->expandParallelGroups(
            subscriptionId: $subscriptionId,
            data: $data,
            entries: $entries
        );
        $warnings = $warnings->merge($parallelWarnings);

        [$entries, $roomWarnings] = $this->assignRooms(
            subscriptionId: $subscriptionId,
            data: $data,
            entries: $entries
        );
        $warnings = $warnings->merge($roomWarnings);

        $entries = $entries
            ->sortBy(fn (array $entry) => sprintf(
                '%02d:%08d:%08d',
                (int) $entry['weekday'],
                (int) $entry['school_bell_id'],
                (int) ($entry['parallel_group_id'] ?? 0)
            ))
            ->values();

        $result = $this->recalculateResult($baseResult, $entries, $warnings);

        if ($preview) {
            return array_merge($result, [
                'preview' => true,
                'weekly_timetable_id' => $data['weekly_timetable_id'] ?? null,
                'locked_entries_preserved' => $lockedEntries->count(),
            ]);
        }

        return DB::transaction(function () use (
            $subscriptionId,
            $data,
            $entries,
            $result,
            $lockedEntries
        ) {
            $timetable = $this->resolveTimetable($subscriptionId, $data);

            $timetable->entries()->where('is_locked', false)->delete();

            foreach ($entries as $entry) {
                if (! empty($entry['id']) && ! empty($entry['is_locked'])) {
                    continue;
                }

                $timetable->entries()->create(array_merge($entry, [
                    'is_active' => true,
                    'is_substitution' => false,
                    'is_locked' => false,
                ]));
            }

            $timetable->forceFill([
                'is_generated' => true,
                'is_active' => true,
            ])->save();

            return array_merge($result, [
                'preview' => false,
                'weekly_timetable_id' => $timetable->id,
                'locked_entries_preserved' => $lockedEntries->count(),
                'timetable' => $timetable->fresh([
                    'template',
                    'grade',
                    'section',
                    'stream',
                    'entries.bell',
                    'entries.subject',
                    'entries.teacher',
                    'entries.room',
                    'entries.parallelGroup',
                ]),
            ]);
        });
    }

    private function lockedEntries(int $subscriptionId, array $data): Collection
    {
        if (empty($data['weekly_timetable_id'])) {
            return collect();
        }

        return TimetableEntry::query()
            ->forSubscription($subscriptionId)
            ->where('weekly_timetable_id', (int) $data['weekly_timetable_id'])
            ->active()
            ->locked()
            ->get()
            ->map(fn (TimetableEntry $entry) => $entry->only([
                'id',
                'weekday',
                'school_bell_id',
                'teacher_id',
                'subject_id',
                'lesson_id',
                'parallel_group_id',
                'student_group_name',
                'room_id',
                'room_no',
                'is_parallel',
                'is_substitution',
                'substitute_teacher_id',
                'remarks',
                'is_locked',
                'is_active',
            ]));
    }

    private function preserveLockedEntries(Collection $generated, Collection $locked): array
    {
        if ($locked->isEmpty()) {
            return [$generated, collect()];
        }

        $lockedClassSlots = $locked
            ->mapWithKeys(fn (array $entry) => [
                $this->classSlotKey((int) $entry['weekday'], (int) $entry['school_bell_id']) => true,
            ]);

        $lockedTeacherSlots = $locked
            ->filter(fn (array $entry) => filled($entry['teacher_id'] ?? null))
            ->mapWithKeys(fn (array $entry) => [
                $this->resourceSlotKey(
                    (int) $entry['teacher_id'],
                    (int) $entry['weekday'],
                    (int) $entry['school_bell_id']
                ) => true,
            ]);

        $warnings = collect();
        $filtered = $generated->reject(function (array $entry) use (
            $lockedClassSlots,
            $lockedTeacherSlots,
            $warnings
        ) {
            $classKey = $this->classSlotKey(
                (int) $entry['weekday'],
                (int) $entry['school_bell_id']
            );

            $teacherConflict = ! empty($entry['teacher_id'])
                && $lockedTeacherSlots->has($this->resourceSlotKey(
                    (int) $entry['teacher_id'],
                    (int) $entry['weekday'],
                    (int) $entry['school_bell_id']
                ));

            if ($lockedClassSlots->has($classKey) || $teacherConflict) {
                $warnings->push('A generated entry was skipped because it conflicted with a locked timetable entry.');
                return true;
            }

            return false;
        });

        return [$locked->concat($filtered)->values(), $warnings];
    }

    private function expandParallelGroups(
        int $subscriptionId,
        array $data,
        Collection $entries
    ): array {
        $groups = ParallelGroup::query()
            ->where('subscription_id', $subscriptionId)
            ->forGrade((int) $data['grade_id'])
            ->active()
            ->with(['activeItems.subject:id,name', 'activeItems.teacher:id,name'])
            ->get();

        if ($groups->isEmpty()) {
            return [$entries, collect()];
        }

        $streamId = isset($data['stream_id']) ? (int) $data['stream_id'] : null;
        $warnings = collect();

        foreach ($groups as $group) {
            $items = $group->activeItems
                ->filter(fn ($item) => $item->appliesToStream($streamId))
                ->values();

            if ($items->count() < 2 || ! $group->same_period_required) {
                continue;
            }

            $subjectIds = $items->pluck('subject_id')->map(fn ($id) => (int) $id);
            $candidateEntries = $entries
                ->filter(fn (array $entry) => $subjectIds->contains((int) ($entry['subject_id'] ?? 0)))
                ->sortBy(fn (array $entry) => sprintf(
                    '%02d:%08d',
                    (int) $entry['weekday'],
                    (int) $entry['school_bell_id']
                ))
                ->values();

            $requiredSlots = max(1, (int) $group->weekly_periods);
            $anchors = $candidateEntries
                ->unique(fn (array $entry) => $this->classSlotKey(
                    (int) $entry['weekday'],
                    (int) $entry['school_bell_id']
                ))
                ->take($requiredSlots)
                ->values();

            if ($anchors->count() < $requiredSlots) {
                $warnings->push("Parallel group '{$group->name}' could only use {$anchors->count()} of {$requiredSlots} required slots.");
            }

            $entries = $entries->reject(
                fn (array $entry) => $subjectIds->contains((int) ($entry['subject_id'] ?? 0))
                    && ($entry['student_group_name'] ?? null) === $group->name
            )->values();

            foreach ($anchors as $anchor) {
                foreach ($items as $item) {
                    $entries->push([
                        'weekday' => (int) $anchor['weekday'],
                        'school_bell_id' => (int) $anchor['school_bell_id'],
                        'teacher_id' => $item->teacher_id ? (int) $item->teacher_id : null,
                        'subject_id' => (int) $item->subject_id,
                        'parallel_group_id' => (int) $group->id,
                        'student_group_name' => $item->displayGroupName(),
                        'room_no' => $item->room_no,
                        'is_parallel' => true,
                        'remarks' => 'Generated parallel group: ' . $group->name,
                    ]);
                }
            }
        }

        return [$entries->values(), $warnings];
    }

    private function assignRooms(
        int $subscriptionId,
        array $data,
        Collection $entries
    ): array {
        $rooms = TimetableRoom::query()
            ->where('subscription_id', $subscriptionId)
            ->active()
            ->ordered()
            ->get();

        if ($rooms->isEmpty()) {
            return [$entries, collect()];
        }

        $excludedTimetableId = isset($data['weekly_timetable_id'])
            ? (int) $data['weekly_timetable_id']
            : null;

        $occupied = TimetableEntry::query()
            ->forSubscription($subscriptionId)
            ->active()
            ->whereNotNull('room_id')
            ->whereHas('weeklyTimetable', function ($query) use ($data, $excludedTimetableId) {
                $query->when(
                    isset($data['academic_year_id']),
                    fn ($query) => $query->where('academic_year_id', $data['academic_year_id'])
                )->when(
                    $excludedTimetableId !== null,
                    fn ($query) => $query->where('id', '!=', $excludedTimetableId)
                );
            })
            ->get(['room_id', 'weekday', 'school_bell_id'])
            ->mapWithKeys(fn (TimetableEntry $entry) => [
                $this->resourceSlotKey(
                    (int) $entry->room_id,
                    (int) $entry->weekday,
                    (int) $entry->school_bell_id
                ) => true,
            ])
            ->all();

        $allocations = SubjectPeriodAllocation::query()
            ->forSubscription($subscriptionId)
            ->forAcademicYear(isset($data['academic_year_id']) ? (int) $data['academic_year_id'] : null)
            ->forClass(
                (int) $data['grade_id'],
                isset($data['section_id']) ? (int) $data['section_id'] : null,
                isset($data['stream_id']) ? (int) $data['stream_id'] : null
            )
            ->get()
            ->keyBy('subject_id');

        $warnings = collect();

        $entries = $entries->map(function (array $entry) use (
            $rooms,
            $allocations,
            &$occupied,
            $warnings
        ) {
            if (! empty($entry['room_id']) || ! empty($entry['is_locked'])) {
                if (! empty($entry['room_id'])) {
                    $occupied[$this->resourceSlotKey(
                        (int) $entry['room_id'],
                        (int) $entry['weekday'],
                        (int) $entry['school_bell_id']
                    )] = true;
                }

                return $entry;
            }

            $allocation = $allocations->get((int) ($entry['subject_id'] ?? 0));
            $preferredTypes = match ($allocation?->subject_category) {
                'lab' => ['laboratory', 'computer_lab'],
                'activity' => ['activity'],
                default => ['classroom', 'other'],
            };

            $room = $rooms->first(function (TimetableRoom $room) use (
                $entry,
                $preferredTypes,
                $occupied
            ) {
                $key = $this->resourceSlotKey(
                    (int) $room->id,
                    (int) $entry['weekday'],
                    (int) $entry['school_bell_id']
                );

                return in_array($room->room_type, $preferredTypes, true)
                    && $room->supportsSubject(isset($entry['subject_id']) ? (int) $entry['subject_id'] : null)
                    && ! isset($occupied[$key]);
            });

            if (! $room) {
                $warnings->push('No compatible free room was available for one generated timetable entry.');
                return $entry;
            }

            $entry['room_id'] = (int) $room->id;
            $entry['room_no'] = $room->code ?: $room->name;
            $occupied[$this->resourceSlotKey(
                (int) $room->id,
                (int) $entry['weekday'],
                (int) $entry['school_bell_id']
            )] = true;

            return $entry;
        });

        return [$entries, $warnings];
    }

    private function recalculateResult(
        array $baseResult,
        Collection $entries,
        Collection $warnings
    ): array {
        $scheduledBySubject = $entries
            ->filter(fn (array $entry) => ! empty($entry['subject_id']))
            ->countBy(fn (array $entry) => (int) $entry['subject_id']);

        $subjectSummary = collect($baseResult['subject_summary'] ?? [])
            ->map(function (array $summary) use ($scheduledBySubject) {
                $summary['scheduled'] = (int) ($scheduledBySubject[(int) $summary['subject_id']] ?? 0);
                return $summary;
            })
            ->values()
            ->all();

        $requested = (int) ($baseResult['requested_periods'] ?? 0);
        $scheduled = collect($subjectSummary)->sum('scheduled');

        return array_merge($baseResult, [
            'entries' => $entries->all(),
            'scheduled_periods' => $scheduled,
            'unscheduled_periods' => max(0, $requested - $scheduled),
            'completion_percentage' => $requested > 0
                ? round(min(100, ($scheduled / $requested) * 100), 2)
                : 100,
            'subject_summary' => $subjectSummary,
            'warnings' => $warnings->filter()->unique()->values()->all(),
        ]);
    }

    private function resolveTimetable(int $subscriptionId, array $data): WeeklyTimetable
    {
        if (! empty($data['weekly_timetable_id'])) {
            return WeeklyTimetable::query()
                ->forSubscription($subscriptionId)
                ->whereKey((int) $data['weekly_timetable_id'])
                ->firstOrFail();
        }

        $timetable = new WeeklyTimetable();
        $timetable->fill([
            'subscription_id' => $subscriptionId,
            'academic_year_id' => $data['academic_year_id'] ?? null,
            'name' => $data['name'] ?? 'Generated Timetable - Grade ' . $data['grade_id'],
            'grade_id' => $data['grade_id'],
            'section_id' => $data['section_id'] ?? null,
            'stream_id' => $data['stream_id'] ?? null,
            'timetable_template_id' => $data['timetable_template_id'],
            'effective_from' => $data['effective_from'] ?? now()->toDateString(),
            'is_active' => true,
            'is_generated' => false,
        ]);
        $timetable->save();

        return $timetable;
    }

    private function classSlotKey(int $weekday, int $bellId): string
    {
        return "{$weekday}:{$bellId}";
    }

    private function resourceSlotKey(int $resourceId, int $weekday, int $bellId): string
    {
        return "{$resourceId}:{$weekday}:{$bellId}";
    }
}