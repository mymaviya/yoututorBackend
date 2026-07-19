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

class OptimizedTimetableGeneratorService
{
    public function __construct(
        protected AdvancedTimetableGeneratorService $generator
    ) {}

    public function generate(int $subscriptionId, array $data, bool $preview = false): array
    {
        $attempts = max(1, min(10, (int) ($data['optimization_attempts'] ?? 3)));
        $baseResult = $this->generator->generate($subscriptionId, $data, true);
        $candidates = collect();

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            $candidate = $this->repair(
                subscriptionId: $subscriptionId,
                data: $data,
                result: $baseResult,
                seed: $attempt
            );

            $candidate['optimization_attempt'] = $attempt;
            $candidate['optimization_score'] = $this->score($candidate);
            $candidates->push($candidate);

            if ((int) ($candidate['unscheduled_periods'] ?? 0) === 0) {
                break;
            }
        }

        $best = $candidates
            ->sortByDesc('optimization_score')
            ->first() ?? $baseResult;

        $best['optimization_attempts_requested'] = $attempts;
        $best['optimization_attempts_executed'] = $candidates->count();
        $best['optimized'] = $candidates->count() > 0;

        if (! ($data['allow_partial'] ?? false)
            && (int) ($best['unscheduled_periods'] ?? 0) > 0) {
            throw ValidationException::withMessages([
                'generation' => 'A complete timetable could not be generated after optimization.',
                'conflicts' => $best['warnings'] ?? [],
            ]);
        }

        if ($preview) {
            return array_merge($best, [
                'preview' => true,
                'weekly_timetable_id' => $data['weekly_timetable_id'] ?? null,
            ]);
        }

        return $this->persist($subscriptionId, $data, $best);
    }

    private function repair(
        int $subscriptionId,
        array $data,
        array $result,
        int $seed
    ): array {
        $entries = collect($result['entries'] ?? [])->values();
        $warnings = collect($result['warnings'] ?? []);
        $workingDays = max(1, min(7, (int) ($data['working_days'] ?? 6)));

        $bells = SchoolBell::query()
            ->forSubscription($subscriptionId)
            ->active()
            ->teachingPeriods()
            ->ordered()
            ->get()
            ->values();

        $allocations = SubjectPeriodAllocation::query()
            ->forSubscription($subscriptionId)
            ->forAcademicYear(isset($data['academic_year_id']) ? (int) $data['academic_year_id'] : null)
            ->forClass(
                (int) $data['grade_id'],
                isset($data['section_id']) ? (int) $data['section_id'] : null,
                isset($data['stream_id']) ? (int) $data['stream_id'] : null
            )
            ->active()
            ->where('weekly_periods', '>', 0)
            ->with('subject:id,name')
            ->ordered()
            ->get();

        $availability = TeacherAvailability::query()
            ->where('subscription_id', $subscriptionId)
            ->active()
            ->get()
            ->keyBy(fn (TeacherAvailability $item) => $this->resourceKey(
                (int) $item->teacher_id,
                (int) $item->weekday,
                (int) $item->school_bell_id
            ));

        $occupiedTeachers = $this->occupiedTeacherSlots($subscriptionId, $data);
        $classSlots = $entries->mapWithKeys(fn (array $entry) => [
            $this->slotKey((int) $entry['weekday'], (int) $entry['school_bell_id']) => true,
        ])->all();

        $teacherSlots = $occupiedTeachers;
        foreach ($entries as $entry) {
            if (! empty($entry['teacher_id'])) {
                $teacherSlots[$this->resourceKey(
                    (int) $entry['teacher_id'],
                    (int) $entry['weekday'],
                    (int) $entry['school_bell_id']
                )] = true;
            }
        }

        $scheduledBySubject = $entries
            ->groupBy(fn (array $entry) => (int) ($entry['subject_id'] ?? 0))
            ->map->count()
            ->all();

        foreach ($allocations as $allocation) {
            $missing = max(
                0,
                (int) $allocation->weekly_periods
                    - (int) ($scheduledBySubject[$allocation->subject_id] ?? 0)
            );

            for ($index = 0; $index < $missing; $index++) {
                $candidate = $this->findRepairSlot(
                    allocation: $allocation,
                    bells: $bells,
                    workingDays: $workingDays,
                    classSlots: $classSlots,
                    teacherSlots: $teacherSlots,
                    availability: $availability,
                    entries: $entries,
                    seed: $seed + $index
                );

                if ($candidate === null) {
                    $warnings->push(
                        'Optimization could not place one period of '
                        . ($allocation->subject?->name ?? "Subject #{$allocation->subject_id}")
                        . '.'
                    );
                    continue;
                }

                $entry = [
                    'weekday' => $candidate['weekday'],
                    'school_bell_id' => $candidate['school_bell_id'],
                    'teacher_id' => $allocation->preferred_teacher_id
                        ? (int) $allocation->preferred_teacher_id
                        : null,
                    'subject_id' => (int) $allocation->subject_id,
                    'student_group_name' => $allocation->parallel_group_code,
                    'is_parallel' => false,
                    'remarks' => 'Placed by optimization repair',
                ];

                $entries->push($entry);
                $classSlots[$this->slotKey($entry['weekday'], $entry['school_bell_id'])] = true;

                if ($entry['teacher_id'] !== null) {
                    $teacherSlots[$this->resourceKey(
                        $entry['teacher_id'],
                        $entry['weekday'],
                        $entry['school_bell_id']
                    )] = true;
                }
            }
        }

        $entries = $entries
            ->sortBy(fn (array $entry) => sprintf(
                '%02d:%08d:%08d',
                (int) $entry['weekday'],
                (int) $entry['school_bell_id'],
                (int) ($entry['subject_id'] ?? 0)
            ))
            ->values();

        $requested = (int) $allocations->sum('weekly_periods');
        $scheduled = $entries->count();
        $unscheduled = max(0, $requested - $scheduled);

        $result['entries'] = $entries->all();
        $result['requested_periods'] = $requested;
        $result['scheduled_periods'] = $scheduled;
        $result['unscheduled_periods'] = $unscheduled;
        $result['completion_percentage'] = $requested > 0
            ? round(($scheduled / $requested) * 100, 2)
            : 100;
        $result['warnings'] = $warnings->unique()->values()->all();

        return $result;
    }

    private function findRepairSlot(
        SubjectPeriodAllocation $allocation,
        Collection $bells,
        int $workingDays,
        array $classSlots,
        array $teacherSlots,
        Collection $availability,
        Collection $entries,
        int $seed
    ): ?array {
        $teacherId = $allocation->preferred_teacher_id
            ? (int) $allocation->preferred_teacher_id
            : null;
        $candidates = collect();

        for ($weekday = 1; $weekday <= $workingDays; $weekday++) {
            $dailyCount = $entries->filter(
                fn (array $entry) => (int) $entry['weekday'] === $weekday
                    && (int) ($entry['subject_id'] ?? 0) === (int) $allocation->subject_id
            )->count();

            if ($dailyCount >= max(1, (int) $allocation->max_periods_per_day)) {
                continue;
            }

            foreach ($bells as $bellIndex => $bell) {
                $bellId = (int) $bell->id;
                if (isset($classSlots[$this->slotKey($weekday, $bellId)])) {
                    continue;
                }

                if ($teacherId !== null) {
                    $teacherKey = $this->resourceKey($teacherId, $weekday, $bellId);
                    if (isset($teacherSlots[$teacherKey])
                        || $availability->get($teacherKey)?->isUnavailable()) {
                        continue;
                    }
                }

                $score = 1000 - ($dailyCount * 150) - ($weekday * 3) - $bellIndex;
                $score += abs(crc32("{$seed}:{$allocation->id}:{$weekday}:{$bellId}")) % 31;

                if ($allocation->prefer_morning) {
                    $score += max(0, 30 - ($bellIndex * 5));
                }
                if ($allocation->prefer_last_period && $bellIndex === $bells->count() - 1) {
                    $score += 35;
                }
                if ($allocation->prefer_saturday && $weekday === 6) {
                    $score += 40;
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

    private function occupiedTeacherSlots(int $subscriptionId, array $data): array
    {
        $excludedTimetableId = isset($data['weekly_timetable_id'])
            ? (int) $data['weekly_timetable_id']
            : null;

        return TimetableEntry::query()
            ->active()
            ->forSubscription($subscriptionId)
            ->whereHas('weeklyTimetable', function ($query) use ($data, $excludedTimetableId) {
                $query->when(
                    isset($data['academic_year_id']),
                    fn ($query) => $query->where('academic_year_id', $data['academic_year_id'])
                )->when(
                    $excludedTimetableId !== null,
                    fn ($query) => $query->where('id', '!=', $excludedTimetableId)
                );
            })
            ->where(function ($query) {
                $query->whereNotNull('teacher_id')
                    ->orWhereNotNull('substitute_teacher_id');
            })
            ->get()
            ->mapWithKeys(function (TimetableEntry $entry) {
                $teacherId = $entry->effectiveTeacherId();

                return $teacherId === null ? [] : [
                    $this->resourceKey(
                        $teacherId,
                        (int) $entry->weekday,
                        (int) $entry->school_bell_id
                    ) => true,
                ];
            })
            ->all();
    }

    private function score(array $result): int
    {
        $scheduled = (int) ($result['scheduled_periods'] ?? 0);
        $unscheduled = (int) ($result['unscheduled_periods'] ?? 0);
        $warnings = count((array) ($result['warnings'] ?? []));
        $gaps = $this->teacherGapCount(collect($result['entries'] ?? []));

        return ($scheduled * 100000)
            - ($unscheduled * 1000000)
            - ($warnings * 1000)
            - ($gaps * 100);
    }

    private function teacherGapCount(Collection $entries): int
    {
        $gaps = 0;
        $groups = $entries
            ->filter(fn (array $entry) => ! empty($entry['teacher_id']))
            ->groupBy(fn (array $entry) => $entry['teacher_id'] . ':' . $entry['weekday']);

        foreach ($groups as $group) {
            $bells = $group->pluck('school_bell_id')->map('intval')->sort()->values();
            for ($index = 1; $index < $bells->count(); $index++) {
                if ($bells[$index] - $bells[$index - 1] > 1) {
                    $gaps++;
                }
            }
        }

        return $gaps;
    }

    private function persist(int $subscriptionId, array $data, array $result): array
    {
        return DB::transaction(function () use ($subscriptionId, $data, $result) {
            $template = TimetableTemplate::query()
                ->where('subscription_id', $subscriptionId)
                ->whereKey($data['timetable_template_id'])
                ->where('is_active', true)
                ->firstOrFail();

            $timetable = isset($data['weekly_timetable_id'])
                ? WeeklyTimetable::query()
                    ->forSubscription($subscriptionId)
                    ->whereKey($data['weekly_timetable_id'])
                    ->firstOrFail()
                : new WeeklyTimetable();

            $timetable->fill([
                'subscription_id' => $subscriptionId,
                'academic_year_id' => $data['academic_year_id'] ?? null,
                'name' => $data['name'] ?? 'Optimized Timetable - Grade ' . (int) $data['grade_id'],
                'grade_id' => (int) $data['grade_id'],
                'section_id' => $data['section_id'] ?? null,
                'stream_id' => $data['stream_id'] ?? null,
                'timetable_template_id' => $template->id,
                'effective_from' => $data['effective_from'] ?? now()->toDateString(),
                'is_active' => true,
                'is_generated' => true,
            ])->save();

            $timetable->entries()->where('is_locked', false)->delete();

            foreach ((array) ($result['entries'] ?? []) as $entry) {
                if (! empty($entry['id']) && ! empty($entry['is_locked'])) {
                    continue;
                }

                $timetable->entries()->create(array_merge($entry, [
                    'is_active' => true,
                    'is_substitution' => false,
                    'is_locked' => false,
                ]));
            }

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
                    'entries.room',
                    'entries.parallelGroup',
                ]),
            ]);
        });
    }

    private function slotKey(int $weekday, int $bellId): string
    {
        return "{$weekday}:{$bellId}";
    }

    private function resourceKey(int $resourceId, int $weekday, int $bellId): string
    {
        return "{$resourceId}:{$weekday}:{$bellId}";
    }
}
