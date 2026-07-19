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
    public function __construct(
        protected TimetableConstraintResolver $constraints
    ) {}

    public function generate(int $subscriptionId, array $data, bool $preview = false): array
    {
        $workingDays = (int) ($data['working_days'] ?? 6);
        $allowPartial = (bool) ($data['allow_partial'] ?? false);
        $academicYearId = isset($data['academic_year_id'])
            ? (int) $data['academic_year_id']
            : null;
        $effectiveDate = $data['effective_from'] ?? now()->toDateString();

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
            ->forAcademicYear($academicYearId)
            ->forClass(
                (int) $data['grade_id'],
                isset($data['section_id']) ? (int) $data['section_id'] : null,
                isset($data['stream_id']) ? (int) $data['stream_id'] : null
            )
            ->active()
            ->where('weekly_periods', '>', 0)
            ->with(['subject:id,name', 'preferredTeacher:id,name'])
            ->ordered()
            ->get();

        if ($allocations->isEmpty()) {
            throw ValidationException::withMessages([
                'allocations' => 'No schedulable subject-period allocations were found for the selected class.',
            ]);
        }

        $requiredPeriods = (int) $allocations->sum('weekly_periods');
        $availableCapacity = $bells->count() * $workingDays;

        if ($requiredPeriods > $availableCapacity && ! $allowPartial) {
            throw ValidationException::withMessages([
                'allocations' => "The class requires {$requiredPeriods} weekly periods but only {$availableCapacity} slots are available.",
            ]);
        }

        $this->constraints->load($subscriptionId, $academicYearId, $effectiveDate);

        $teacherIds = $allocations
            ->pluck('preferred_teacher_id')
            ->filter()
            ->unique()
            ->values();

        $availability = TeacherAvailability::query()
            ->where('subscription_id', $subscriptionId)
            ->active()
            ->whereIn('teacher_id', $teacherIds)
            ->get()
            ->keyBy(fn (TeacherAvailability $item) => $this->slotKey(
                (int) $item->teacher_id,
                (int) $item->weekday,
                (int) $item->school_bell_id
            ));

        $bellIndex = $bells->values()->mapWithKeys(
            fn (SchoolBell $bell, int $index) => [(int) $bell->id => $index]
        )->all();

        $occupiedState = $this->occupiedTeacherState(
            subscriptionId: $subscriptionId,
            academicYearId: $academicYearId,
            excludedTimetableId: isset($data['weekly_timetable_id'])
                ? (int) $data['weekly_timetable_id']
                : null,
            bellIndex: $bellIndex
        );

        $result = $this->buildSchedule(
            allocations: $allocations,
            bells: $bells,
            availability: $availability,
            occupiedState: $occupiedState,
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
        array $occupiedState,
        int $workingDays
    ): array {
        $entries = [];
        $classSlots = [];
        $teacherSlots = $occupiedState['slots'];
        $teacherDailyCounts = $occupiedState['daily_counts'];
        $teacherWeeklyCounts = $occupiedState['weekly_counts'];
        $teacherDayIndexes = $occupiedState['day_indexes'];
        $dailySubjectCounts = [];
        $scheduledBySubject = [];
        $warnings = [];

        $units = $this->buildSchedulingUnits($allocations);

        foreach ($units as $unit) {
            /** @var SubjectPeriodAllocation $allocation */
            $allocation = $unit['allocation'];
            $size = (int) $unit['size'];

            $candidate = $this->findBestBlock(
                allocation: $allocation,
                size: $size,
                bells: $bells,
                availability: $availability,
                classSlots: $classSlots,
                teacherSlots: $teacherSlots,
                teacherDailyCounts: $teacherDailyCounts,
                teacherWeeklyCounts: $teacherWeeklyCounts,
                teacherDayIndexes: $teacherDayIndexes,
                dailySubjectCounts: $dailySubjectCounts,
                workingDays: $workingDays
            );

            if ($candidate === null) {
                $subjectName = $allocation->subject?->name ?? "Subject #{$allocation->subject_id}";
                $label = $size === 2 ? 'double period' : 'period';
                $warnings[] = "Could not place one {$label} of {$subjectName}.";
                continue;
            }

            $weekday = (int) $candidate['weekday'];
            $teacherId = $allocation->preferred_teacher_id
                ? (int) $allocation->preferred_teacher_id
                : null;

            foreach ($candidate['bells'] as $bellPosition => $bell) {
                $bellId = (int) $bell->id;
                $bellIndex = (int) $candidate['bell_indexes'][$bellPosition];

                $entries[] = [
                    'weekday' => $weekday,
                    'school_bell_id' => $bellId,
                    'teacher_id' => $teacherId,
                    'subject_id' => (int) $allocation->subject_id,
                    'student_group_name' => $allocation->parallel_group_code,
                    'remarks' => $size === 2 ? 'Generated double period' : null,
                ];

                $classSlots[$this->classSlotKey($weekday, $bellId)] = true;

                if ($teacherId !== null) {
                    $teacherSlots[$this->slotKey($teacherId, $weekday, $bellId)] = true;
                    $teacherDailyCounts[$teacherId][$weekday] =
                        ($teacherDailyCounts[$teacherId][$weekday] ?? 0) + 1;
                    $teacherWeeklyCounts[$teacherId] =
                        ($teacherWeeklyCounts[$teacherId] ?? 0) + 1;
                    $teacherDayIndexes[$teacherId][$weekday][] = $bellIndex;
                    $teacherDayIndexes[$teacherId][$weekday] = array_values(array_unique(
                        $teacherDayIndexes[$teacherId][$weekday]
                    ));
                }

                $dailySubjectCounts[$weekday][$allocation->subject_id] =
                    ($dailySubjectCounts[$weekday][$allocation->subject_id] ?? 0) + 1;
                $scheduledBySubject[$allocation->subject_id] =
                    ($scheduledBySubject[$allocation->subject_id] ?? 0) + 1;
            }
        }

        usort(
            $entries,
            fn (array $a, array $b) => [$a['weekday'], $a['school_bell_id']]
                <=> [$b['weekday'], $b['school_bell_id']]
        );

        $requested = (int) $allocations->sum('weekly_periods');
        $scheduled = count($entries);

        return [
            'entries' => $entries,
            'requested_periods' => $requested,
            'scheduled_periods' => $scheduled,
            'unscheduled_periods' => max(0, $requested - $scheduled),
            'completion_percentage' => $requested > 0
                ? round(($scheduled / $requested) * 100, 2)
                : 100,
            'subject_summary' => $allocations->map(
                fn (SubjectPeriodAllocation $allocation) => [
                    'subject_id' => $allocation->subject_id,
                    'subject_name' => $allocation->subject?->name,
                    'requested' => (int) $allocation->weekly_periods,
                    'scheduled' => (int) ($scheduledBySubject[$allocation->subject_id] ?? 0),
                    'double_period_preference' => (bool) $allocation->prefer_double_period,
                ]
            )->values()->all(),
            'applied_rules' => $this->constraints->appliedRules(),
            'warnings' => array_values(array_unique($warnings)),
        ];
    }

    private function buildSchedulingUnits(Collection $allocations): Collection
    {
        return $allocations
            ->flatMap(function (SubjectPeriodAllocation $allocation) {
                $periods = max(0, (int) $allocation->weekly_periods);
                $units = collect();

                if ($allocation->prefer_double_period) {
                    $pairs = intdiv($periods, 2);
                    for ($index = 0; $index < $pairs; $index++) {
                        $units->push(['allocation' => $allocation, 'size' => 2]);
                    }

                    if ($periods % 2 === 1) {
                        $units->push(['allocation' => $allocation, 'size' => 1]);
                    }
                } else {
                    for ($index = 0; $index < $periods; $index++) {
                        $units->push(['allocation' => $allocation, 'size' => 1]);
                    }
                }

                return $units;
            })
            ->sortByDesc(function (array $unit) {
                /** @var SubjectPeriodAllocation $allocation */
                $allocation = $unit['allocation'];

                return ((int) $allocation->priority * 100)
                    + ((int) $unit['size'] * 50)
                    + ($allocation->preferred_teacher_id ? 25 : 0)
                    + ($allocation->is_parallel_subject ? 5 : 0);
            })
            ->values();
    }

    private function findBestBlock(
        SubjectPeriodAllocation $allocation,
        int $size,
        Collection $bells,
        Collection $availability,
        array $classSlots,
        array $teacherSlots,
        array $teacherDailyCounts,
        array $teacherWeeklyCounts,
        array $teacherDayIndexes,
        array $dailySubjectCounts,
        int $workingDays
    ): ?array {
        $candidates = collect();
        $teacherId = $allocation->preferred_teacher_id
            ? (int) $allocation->preferred_teacher_id
            : null;
        $maxTeacherDaily = max(1, $this->constraints->integer(
            'teacher.max_daily_periods',
            $bells->count()
        ));
        $maxTeacherWeekly = max(1, $this->constraints->integer(
            'teacher.max_weekly_periods',
            $bells->count() * $workingDays
        ));
        $maxConsecutive = max(1, $this->constraints->integer(
            'teacher.max_consecutive_periods',
            $bells->count()
        ));
        $spreadSubjects = $this->constraints->boolean('subject.spread_across_days', true);

        for ($weekday = 1; $weekday <= $workingDays; $weekday++) {
            for ($start = 0; $start <= $bells->count() - $size; $start++) {
                $block = $bells->slice($start, $size)->values();

                if ($size === 2 && ! $this->isConsecutiveTeachingBlock($block)) {
                    continue;
                }

                $bellIds = $block->pluck('id')->map(fn ($id) => (int) $id)->all();
                $bellIndexes = range($start, $start + $size - 1);

                if ($this->hasClassConflict($weekday, $bellIds, $classSlots)) {
                    continue;
                }

                $dailySubjectCount = (int) (
                    $dailySubjectCounts[$weekday][$allocation->subject_id] ?? 0
                );

                if ($dailySubjectCount + $size > (int) $allocation->max_periods_per_day) {
                    continue;
                }

                if ($teacherId !== null && $this->hasTeacherConflict(
                    teacherId: $teacherId,
                    weekday: $weekday,
                    bellIds: $bellIds,
                    availability: $availability,
                    teacherSlots: $teacherSlots
                )) {
                    continue;
                }

                if ($teacherId !== null) {
                    $dailyTeacherCount = (int) ($teacherDailyCounts[$teacherId][$weekday] ?? 0);
                    $weeklyTeacherCount = (int) ($teacherWeeklyCounts[$teacherId] ?? 0);

                    if ($dailyTeacherCount + $size > $maxTeacherDaily) {
                        continue;
                    }

                    if ($weeklyTeacherCount + $size > $maxTeacherWeekly) {
                        continue;
                    }

                    $prospectiveIndexes = array_merge(
                        $teacherDayIndexes[$teacherId][$weekday] ?? [],
                        $bellIndexes
                    );

                    if ($this->maximumConsecutiveRun($prospectiveIndexes) > $maxConsecutive) {
                        continue;
                    }
                }

                $hardViolation = $this->hasHardBlockedSlot(
                    allocation: $allocation,
                    teacherId: $teacherId,
                    weekday: $weekday,
                    bellIds: $bellIds
                );

                if ($hardViolation) {
                    continue;
                }

                $score = 1000 - ($dailySubjectCount * 150) - ($weekday * 2) - $start;

                if ($spreadSubjects && $dailySubjectCount > 0) {
                    $score -= 100;
                }

                if ($allocation->prefer_morning) {
                    $score += max(0, 30 - ($start * 5));
                }

                if ($allocation->prefer_last_period && $start + $size === $bells->count()) {
                    $score += 35;
                }

                if ($allocation->prefer_saturday && $weekday === 6) {
                    $score += 40;
                }

                if ($size === 2 && $allocation->prefer_double_period) {
                    $score += 80;
                }

                if ($teacherId !== null) {
                    foreach ($bellIds as $bellId) {
                        if ($availability->get($this->slotKey($teacherId, $weekday, $bellId))?->isPreferred()) {
                            $score += 25;
                        }
                    }
                }

                $score += $this->softRuleScore(
                    allocation: $allocation,
                    teacherId: $teacherId,
                    weekday: $weekday,
                    bellIds: $bellIds
                );

                $candidates->push([
                    'weekday' => $weekday,
                    'bells' => $block->all(),
                    'bell_indexes' => $bellIndexes,
                    'score' => $score,
                ]);
            }
        }

        return $candidates->sortByDesc('score')->first();
    }

    private function hasClassConflict(int $weekday, array $bellIds, array $classSlots): bool
    {
        foreach ($bellIds as $bellId) {
            if (isset($classSlots[$this->classSlotKey($weekday, $bellId)])) {
                return true;
            }
        }

        return false;
    }

    private function hasTeacherConflict(
        int $teacherId,
        int $weekday,
        array $bellIds,
        Collection $availability,
        array $teacherSlots
    ): bool {
        foreach ($bellIds as $bellId) {
            $key = $this->slotKey($teacherId, $weekday, $bellId);

            if (isset($teacherSlots[$key]) || $availability->get($key)?->isUnavailable()) {
                return true;
            }
        }

        return false;
    }

    private function isConsecutiveTeachingBlock(Collection $block): bool
    {
        if ($block->count() !== 2) {
            return true;
        }

        /** @var SchoolBell $first */
        $first = $block->get(0);
        /** @var SchoolBell $second */
        $second = $block->get(1);

        $periodsConsecutive = $first->period_number === null
            || $second->period_number === null
            || (int) $second->period_number === (int) $first->period_number + 1;

        $timesTouch = blank($first->end_time)
            || blank($second->start_time)
            || substr((string) $first->end_time, 0, 5) === substr((string) $second->start_time, 0, 5);

        return $periodsConsecutive && $timesTouch;
    }

    private function hasHardBlockedSlot(
        SubjectPeriodAllocation $allocation,
        ?int $teacherId,
        int $weekday,
        array $bellIds
    ): bool {
        foreach ($bellIds as $bellId) {
            if ($this->slotMatches(
                $this->constraints->blockedClassSlots(),
                $weekday,
                $bellId
            ) && $this->constraints->isHard('class.blocked_slots')) {
                return true;
            }

            if ($teacherId !== null && $this->slotMatches(
                $this->constraints->blockedTeacherSlots(),
                $weekday,
                $bellId,
                teacherId: $teacherId
            ) && $this->constraints->isHard('teacher.blocked_slots')) {
                return true;
            }

            if ($this->slotMatches(
                $this->constraints->blockedSubjectSlots(),
                $weekday,
                $bellId,
                subjectId: (int) $allocation->subject_id
            ) && $this->constraints->isHard('subject.blocked_slots')) {
                return true;
            }
        }

        return false;
    }

    private function softRuleScore(
        SubjectPeriodAllocation $allocation,
        ?int $teacherId,
        int $weekday,
        array $bellIds
    ): int {
        $score = 0;

        foreach ($bellIds as $bellId) {
            if (! $this->constraints->isHard('class.blocked_slots') && $this->slotMatches(
                $this->constraints->blockedClassSlots(),
                $weekday,
                $bellId
            )) {
                $score -= $this->constraints->priority('class.blocked_slots') * 20;
            }

            if ($teacherId !== null
                && ! $this->constraints->isHard('teacher.blocked_slots')
                && $this->slotMatches(
                    $this->constraints->blockedTeacherSlots(),
                    $weekday,
                    $bellId,
                    teacherId: $teacherId
                )) {
                $score -= $this->constraints->priority('teacher.blocked_slots') * 20;
            }

            if (! $this->constraints->isHard('subject.blocked_slots') && $this->slotMatches(
                $this->constraints->blockedSubjectSlots(),
                $weekday,
                $bellId,
                subjectId: (int) $allocation->subject_id
            )) {
                $score -= $this->constraints->priority('subject.blocked_slots') * 20;
            }
        }

        return $score;
    }

    private function slotMatches(
        array $slots,
        int $weekday,
        int $bellId,
        ?int $teacherId = null,
        ?int $subjectId = null
    ): bool {
        foreach ($slots as $slot) {
            if ((int) $slot['weekday'] !== $weekday
                || (int) $slot['school_bell_id'] !== $bellId) {
                continue;
            }

            if ($teacherId !== null && (int) ($slot['teacher_id'] ?? 0) !== $teacherId) {
                continue;
            }

            if ($subjectId !== null && (int) ($slot['subject_id'] ?? 0) !== $subjectId) {
                continue;
            }

            return true;
        }

        return false;
    }

    private function maximumConsecutiveRun(array $indexes): int
    {
        $indexes = array_values(array_unique(array_map('intval', $indexes)));
        sort($indexes);

        $maximum = 0;
        $current = 0;
        $previous = null;

        foreach ($indexes as $index) {
            $current = $previous !== null && $index === $previous + 1
                ? $current + 1
                : 1;
            $maximum = max($maximum, $current);
            $previous = $index;
        }

        return $maximum;
    }

    private function occupiedTeacherState(
        int $subscriptionId,
        ?int $academicYearId,
        ?int $excludedTimetableId,
        array $bellIndex
    ): array {
        $state = [
            'slots' => [],
            'daily_counts' => [],
            'weekly_counts' => [],
            'day_indexes' => [],
        ];

        $entries = TimetableEntry::query()
            ->active()
            ->forSubscription($subscriptionId)
            ->whereHas('weeklyTimetable', function ($query) use ($academicYearId, $excludedTimetableId) {
                $query->when(
                    $academicYearId !== null,
                    fn ($query) => $query->where('academic_year_id', $academicYearId)
                )->when(
                    $excludedTimetableId !== null,
                    fn ($query) => $query->where('id', '!=', $excludedTimetableId)
                );
            })
            ->where(function ($query) {
                $query->whereNotNull('teacher_id')
                    ->orWhereNotNull('substitute_teacher_id');
            })
            ->get([
                'teacher_id',
                'substitute_teacher_id',
                'is_substitution',
                'weekday',
                'school_bell_id',
            ]);

        foreach ($entries as $entry) {
            $teacherId = $entry->effectiveTeacherId();
            if ($teacherId === null) {
                continue;
            }

            $weekday = (int) $entry->weekday;
            $bellId = (int) $entry->school_bell_id;
            $state['slots'][$this->slotKey($teacherId, $weekday, $bellId)] = true;
            $state['daily_counts'][$teacherId][$weekday] =
                ($state['daily_counts'][$teacherId][$weekday] ?? 0) + 1;
            $state['weekly_counts'][$teacherId] =
                ($state['weekly_counts'][$teacherId] ?? 0) + 1;

            if (array_key_exists($bellId, $bellIndex)) {
                $state['day_indexes'][$teacherId][$weekday][] = $bellIndex[$bellId];
            }
        }

        return $state;
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
