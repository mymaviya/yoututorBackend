<?php

namespace App\Services\AcademicPlanning;

use App\Models\SchoolBell;
use App\Models\SubjectPeriodAllocation;
use App\Models\TeacherAvailability;
use App\Models\TimetableEntry;
use App\Models\TimetableTemplate;
use App\Models\WeeklyTimetable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AutomaticTimetableGeneratorService
{
    /**
     * Generate or preview a timetable for one class scope.
     */
    public function generate(int $subscriptionId, array $data, bool $preview = false): array
    {
        $workingDays = (int) ($data['working_days'] ?? 6);
        $allowPartial = (bool) ($data['allow_partial'] ?? false);

        $template = TimetableTemplate::query()
            ->where('subscription_id', $subscriptionId)
            ->whereKey($data['timetable_template_id'])
            ->where('is_active', true)
            ->firstOrFail();

        $bells = SchoolBell::query()
            ->forSubscription($subscriptionId)
            ->active()
            ->teachingPeriods()
            ->ordered()
            ->get();

        if ($bells->isEmpty()) {
            throw ValidationException::withMessages([
                'school_bells' => 'No active teaching periods are available. Generate the bell schedule first.',
            ]);
        }

        $allocations = SubjectPeriodAllocation::query()
            ->forSubscription($subscriptionId)
            ->forAcademicYear($data['academic_year_id'] ?? null)
            ->forClass(
                (int) $data['grade_id'],
                $data['section_id'] ?? null,
                $data['stream_id'] ?? null
            )
            ->active()
            ->with(['subject:id,name', 'preferredTeacher:id,name'])
            ->ordered()
            ->get();

        if ($allocations->isEmpty()) {
            throw ValidationException::withMessages([
                'allocations' => 'No active subject-period allocations were found for the selected class.',
            ]);
        }

        $requiredPeriods = $allocations->sum('weekly_periods');
        $availableCapacity = $bells->count() * $workingDays;

        if ($requiredPeriods > $availableCapacity && ! $allowPartial) {
            throw ValidationException::withMessages([
                'allocations' => "The class requires {$requiredPeriods} weekly periods but only {$availableCapacity} slots are available.",
            ]);
        }

        $availability = TeacherAvailability::query()
            ->where('subscription_id', $subscriptionId)
            ->active()
            ->whereIn('teacher_id', $allocations->pluck('preferred_teacher_id')->filter()->unique())
            ->get()
            ->keyBy(fn (TeacherAvailability $item) => $this->slotKey(
                (int) $item->teacher_id,
                (int) $item->weekday,
                (int) $item->school_bell_id
            ));

        $occupiedTeacherSlots = $this->occupiedTeacherSlots(
            $subscriptionId,
            $data['academic_year_id'] ?? null,
            isset($data['weekly_timetable_id']) ? (int) $data['weekly_timetable_id'] : null
        );

        $result = $this->buildSchedule(
            allocations: $allocations,
            bells: $bells,
            availability: $availability,
            occupiedTeacherSlots: $occupiedTeacherSlots,
            workingDays: $workingDays
        );

        if ($result['unscheduled_periods'] > 0 && ! $allowPartial) {
            throw ValidationException::withMessages([
                'generation' => 'A complete timetable could not be generated.',
                'conflicts' => $result['warnings'],
            ]);
        }

        if ($preview) {
            return array_merge($result, [
                'preview' => true,
                'weekly_timetable_id' => null,
            ]);
        }

        return DB::transaction(function () use ($subscriptionId, $data, $template, $result) {
            $timetable = $this->resolveTimetable($subscriptionId, $data, $template);

            $timetable->entries()->delete();

            foreach ($result['entries'] as $entry) {
                $timetable->entries()->create(array_merge($entry, [
                    'is_active' => true,
                    'is_parallel' => false,
                    'is_substitution' => false,
                ]));
            }

            $timetable->forceFill([
                'is_generated' => true,
                'is_active' => true,
            ])->save();

            return array_merge($result, [
                'preview' => false,
                'weekly_timetable_id' => $timetable->id,
                'timetable' => $timetable->fresh([
                    'template',
                    'grade',
                    'section',
                    'stream',
                    'entries.bell',
                    'entries.subject',
                    'entries.teacher',
                ]),
            ]);
        });
    }

    private function buildSchedule(
        Collection $allocations,
        Collection $bells,
        Collection $availability,
        array $occupiedTeacherSlots,
        int $workingDays
    ): array {
        $entries = [];
        $classSlots = [];
        $teacherSlots = $occupiedTeacherSlots;
        $dailySubjectCounts = [];
        $scheduledBySubject = [];
        $warnings = [];

        $queue = $allocations
            ->flatMap(function (SubjectPeriodAllocation $allocation) {
                return collect(range(1, max(0, (int) $allocation->weekly_periods)))
                    ->map(fn () => $allocation);
            })
            ->sortByDesc(function (SubjectPeriodAllocation $allocation) {
                $score = ((int) $allocation->priority) * 100;
                $score += $allocation->preferred_teacher_id ? 25 : 0;
                $score += $allocation->prefer_double_period ? 10 : 0;
                $score += $allocation->is_parallel_subject ? 5 : 0;

                return $score;
            })
            ->values();

        foreach ($queue as $allocation) {
            $candidate = $this->findBestSlot(
                allocation: $allocation,
                bells: $bells,
                availability: $availability,
                classSlots: $classSlots,
                teacherSlots: $teacherSlots,
                dailySubjectCounts: $dailySubjectCounts,
                workingDays: $workingDays
            );

            if ($candidate === null) {
                $subjectName = $allocation->subject?->name ?? "Subject #{$allocation->subject_id}";
                $warnings[] = "Could not place one period of {$subjectName}.";
                continue;
            }

            $weekday = $candidate['weekday'];
            $bellId = $candidate['school_bell_id'];
            $teacherId = $allocation->preferred_teacher_id ? (int) $allocation->preferred_teacher_id : null;
            $classKey = $this->classSlotKey($weekday, $bellId);

            $entries[] = [
                'weekday' => $weekday,
                'school_bell_id' => $bellId,
                'teacher_id' => $teacherId,
                'subject_id' => (int) $allocation->subject_id,
                'student_group_name' => $allocation->parallel_group_code,
                'remarks' => null,
            ];

            $classSlots[$classKey] = true;

            if ($teacherId !== null) {
                $teacherSlots[$this->slotKey($teacherId, $weekday, $bellId)] = true;
            }

            $dailySubjectCounts[$weekday][$allocation->subject_id] =
                ($dailySubjectCounts[$weekday][$allocation->subject_id] ?? 0) + 1;
            $scheduledBySubject[$allocation->subject_id] =
                ($scheduledBySubject[$allocation->subject_id] ?? 0) + 1;
        }

        $requested = $allocations->sum('weekly_periods');
        $scheduled = count($entries);

        return [
            'entries' => collect($entries)
                ->sortBy(['weekday', 'school_bell_id'])
                ->values()
                ->all(),
            'requested_periods' => $requested,
            'scheduled_periods' => $scheduled,
            'unscheduled_periods' => max(0, $requested - $scheduled),
            'completion_percentage' => $requested > 0
                ? round(($scheduled / $requested) * 100, 2)
                : 100,
            'subject_summary' => $allocations->map(fn (SubjectPeriodAllocation $allocation) => [
                'subject_id' => $allocation->subject_id,
                'subject_name' => $allocation->subject?->name,
                'requested' => (int) $allocation->weekly_periods,
                'scheduled' => (int) ($scheduledBySubject[$allocation->subject_id] ?? 0),
            ])->values()->all(),
            'warnings' => array_values(array_unique($warnings)),
        ];
    }

    private function findBestSlot(
        SubjectPeriodAllocation $allocation,
        Collection $bells,
        Collection $availability,
        array $classSlots,
        array $teacherSlots,
        array $dailySubjectCounts,
        int $workingDays
    ): ?array {
        $candidates = collect();

        for ($weekday = 1; $weekday <= $workingDays; $weekday++) {
            foreach ($bells as $bellIndex => $bell) {
                $bellId = (int) $bell->id;
                $classKey = $this->classSlotKey($weekday, $bellId);

                if (isset($classSlots[$classKey])) {
                    continue;
                }

                $dailyCount = (int) ($dailySubjectCounts[$weekday][$allocation->subject_id] ?? 0);
                if ($dailyCount >= (int) $allocation->max_periods_per_day) {
                    continue;
                }

                $teacherId = $allocation->preferred_teacher_id
                    ? (int) $allocation->preferred_teacher_id
                    : null;

                if ($teacherId !== null) {
                    $teacherKey = $this->slotKey($teacherId, $weekday, $bellId);
                    if (isset($teacherSlots[$teacherKey])) {
                        continue;
                    }

                    $status = $availability->get($teacherKey);
                    if ($status?->isUnavailable()) {
                        continue;
                    }
                }

                $score = 1000 - ($dailyCount * 150);
                $score -= $weekday * 2;
                $score -= $bellIndex;

                if ($allocation->prefer_morning) {
                    $score += max(0, 30 - ($bellIndex * 5));
                }

                if ($allocation->prefer_last_period && $bellIndex === $bells->count() - 1) {
                    $score += 35;
                }

                if ($allocation->prefer_saturday && $weekday === 6) {
                    $score += 40;
                }

                if ($teacherId !== null && $availability->get($this->slotKey($teacherId, $weekday, $bellId))?->isPreferred()) {
                    $score += 50;
                }

                $candidates->push([
                    'weekday' => $weekday,
                    'school_bell_id' => $bellId,
                    'score' => $score,
                ]);
            }
        }

        return $candidates->sortByDesc('score')->first();
    }

    private function occupiedTeacherSlots(
        int $subscriptionId,
        ?int $academicYearId,
        ?int $excludedTimetableId
    ): array {
        return TimetableEntry::query()
            ->active()
            ->forSubscription($subscriptionId)
            ->whereHas('weeklyTimetable', function ($query) use ($academicYearId, $excludedTimetableId) {
                $query->when(
                    $academicYearId !== null,
                    fn ($query) => $query->where('academic_year_id', $academicYearId)
                )->when(
                    $excludedTimetableId !== null,
                    fn ($query) => $query->whereKeyNot($excludedTimetableId)
                );
            })
            ->whereNotNull('teacher_id')
            ->get(['teacher_id', 'weekday', 'school_bell_id'])
            ->mapWithKeys(fn (TimetableEntry $entry) => [
                $this->slotKey(
                    (int) $entry->teacher_id,
                    (int) $entry->weekday,
                    (int) $entry->school_bell_id
                ) => true,
            ])
            ->all();
    }

    private function resolveTimetable(
        int $subscriptionId,
        array $data,
        TimetableTemplate $template
    ): WeeklyTimetable {
        $timetable = isset($data['weekly_timetable_id'])
            ? WeeklyTimetable::query()
                ->forSubscription($subscriptionId)
                ->whereKey($data['weekly_timetable_id'])
                ->firstOrFail()
            : new WeeklyTimetable();

        $timetable->fill([
            'subscription_id' => $subscriptionId,
            'academic_year_id' => $data['academic_year_id'] ?? null,
            'name' => $data['name'] ?? $this->defaultName($data),
            'grade_id' => $data['grade_id'],
            'section_id' => $data['section_id'] ?? null,
            'stream_id' => $data['stream_id'] ?? null,
            'timetable_template_id' => $template->id,
            'effective_from' => $data['effective_from'] ?? now()->toDateString(),
            'is_active' => true,
            'is_generated' => false,
        ]);
        $timetable->save();

        return $timetable;
    }

    private function defaultName(array $data): string
    {
        return sprintf(
            'Generated Timetable - Grade %d%s',
            (int) $data['grade_id'],
            isset($data['section_id']) ? ' / Section ' . $data['section_id'] : ''
        );
    }

    private function slotKey(int $teacherId, int $weekday, int $bellId): string
    {
        return "{$teacherId}:{$weekday}:{$bellId}";
    }

    private function classSlotKey(int $weekday, int $bellId): string
    {
        return "{$weekday}:{$bellId}";
    }
}
