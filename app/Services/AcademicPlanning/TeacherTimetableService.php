<?php

namespace App\Services\AcademicPlanning;

use App\Models\SchoolBell;
use App\Models\TeacherTimetable;
use App\Models\TimetableEntry;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class TeacherTimetableService
{
    private const WEEKDAYS = [
        'Monday',
        'Tuesday',
        'Wednesday',
        'Thursday',
        'Friday',
        'Saturday',
    ];

    public function teacherTimetable(
        int $teacherId,
        ?int $academicYearId,
        int $subscriptionId
    ): array {
        $teacher = User::query()
            ->where('subscription_id', $subscriptionId)
            ->findOrFail($teacherId);

        $bells = $this->bells($subscriptionId);
        $entries = $this->teacherEntriesQuery(
            teacherId: $teacherId,
            academicYearId: $academicYearId,
            subscriptionId: $subscriptionId,
        )->get();

        return [
            'teacher' => $teacher,
            'bells' => $bells,
            'entries' => $entries,
            'summary' => $this->summary($entries, $bells),
        ];
    }

    public function classTimetable(
        int $gradeId,
        ?int $sectionId,
        ?int $streamId,
        ?int $academicYearId,
        int $subscriptionId
    ): array {
        $bells = $this->bells($subscriptionId);
        $entries = $this->classEntriesQuery(
            gradeId: $gradeId,
            sectionId: $sectionId,
            streamId: $streamId,
            academicYearId: $academicYearId,
            subscriptionId: $subscriptionId,
        )->get();

        return [
            'bells' => $bells,
            'entries' => $entries,
            'summary' => $this->summary($entries, $bells),
        ];
    }

    public function today(
        ?int $teacherId,
        ?int $gradeId,
        ?int $sectionId,
        ?int $streamId,
        ?int $academicYearId,
        int $subscriptionId
    ): array {
        $weekday = now()->format('l');
        $bells = $this->bells($subscriptionId);

        $entries = TimetableEntry::query()
            ->with($this->entryRelations())
            ->where('weekday', $weekday)
            ->where('is_active', true)
            ->when(
                $teacherId !== null,
                fn (Builder $query) => $query->where('teacher_id', $teacherId)
            )
            ->when(
                $gradeId !== null,
                fn (Builder $query) => $query->whereHas(
                    'weeklyTimetable',
                    fn (Builder $weekly) => $weekly->where('grade_id', $gradeId)
                )
            )
            ->when(
                $sectionId !== null,
                fn (Builder $query) => $query->whereHas(
                    'weeklyTimetable',
                    fn (Builder $weekly) => $weekly->where('section_id', $sectionId)
                )
            )
            ->when(
                $streamId !== null,
                fn (Builder $query) => $query->whereHas(
                    'weeklyTimetable',
                    fn (Builder $weekly) => $weekly->where('stream_id', $streamId)
                )
            )
            ->when(
                $academicYearId !== null,
                fn (Builder $query) => $query->whereHas(
                    'weeklyTimetable',
                    fn (Builder $weekly) => $weekly->where('academic_year_id', $academicYearId)
                )
            )
            ->whereHas(
                'weeklyTimetable.template',
                fn (Builder $template) => $template->where('subscription_id', $subscriptionId)
            )
            ->orderBy('school_bell_id')
            ->get()
            ->map(fn (TimetableEntry $entry) => $this->transformEntry($entry));

        return [
            'weekday' => $weekday,
            'bells' => $bells,
            'entries' => $entries,
            'summary' => $this->summary($entries, $bells, 1),
        ];
    }

    public function freePeriods(
        int $teacherId,
        ?int $academicYearId,
        int $subscriptionId
    ): array {
        User::query()
            ->where('subscription_id', $subscriptionId)
            ->findOrFail($teacherId);

        $bells = $this->bells($subscriptionId);
        $entries = $this->teacherEntriesQuery(
            teacherId: $teacherId,
            academicYearId: $academicYearId,
            subscriptionId: $subscriptionId,
        )->get();

        $freePeriods = [];

        foreach (self::WEEKDAYS as $weekday) {
            $busyBellIds = $entries
                ->where('weekday', $weekday)
                ->pluck('school_bell_id')
                ->map(fn ($id) => (int) $id)
                ->all();

            $freePeriods[$weekday] = $bells
                ->reject(fn ($bell) => in_array((int) $bell->id, $busyBellIds, true))
                ->values();
        }

        return [
            'teacher_id' => $teacherId,
            'free_periods' => $freePeriods,
        ];
    }

    public function workload(
        int $teacherId,
        ?int $academicYearId,
        int $subscriptionId
    ): array {
        User::query()
            ->where('subscription_id', $subscriptionId)
            ->findOrFail($teacherId);

        $entries = $this->teacherEntriesQuery(
            teacherId: $teacherId,
            academicYearId: $academicYearId,
            subscriptionId: $subscriptionId,
        )->get();

        return [
            'teacher_id' => $teacherId,
            'weekly_periods' => $entries->count(),
            'daily_load' => $entries
                ->groupBy('weekday')
                ->map(fn (Collection $rows) => $rows->count()),
            'subjects' => $entries
                ->pluck('subject_id')
                ->filter()
                ->unique()
                ->count(),
            'substitutions' => $entries
                ->filter(
                    fn ($entry) => (bool) ($entry->timetableEntry?->is_substitution ?? false)
                )
                ->count(),
        ];
    }

    private function teacherEntriesQuery(
        int $teacherId,
        ?int $academicYearId,
        int $subscriptionId
    ): Builder {
        return $this->baseEntriesQuery($subscriptionId)
            ->where('teacher_id', $teacherId)
            ->when(
                $academicYearId !== null,
                fn (Builder $query) => $query->whereHas(
                    'timetableEntry.weeklyTimetable',
                    fn (Builder $weekly) => $weekly->where(
                        'academic_year_id',
                        $academicYearId
                    )
                )
            )
            ->orderByRaw($this->weekdayOrderSql())
            ->orderBy('school_bell_id');
    }

    private function classEntriesQuery(
        int $gradeId,
        ?int $sectionId,
        ?int $streamId,
        ?int $academicYearId,
        int $subscriptionId
    ): Builder {
        return $this->baseEntriesQuery($subscriptionId)
            ->where('grade_id', $gradeId)
            ->when(
                $sectionId !== null,
                fn (Builder $query) => $query->where('section_id', $sectionId)
            )
            ->when(
                $streamId !== null,
                fn (Builder $query) => $query->where('stream_id', $streamId)
            )
            ->when(
                $academicYearId !== null,
                fn (Builder $query) => $query->whereHas(
                    'timetableEntry.weeklyTimetable',
                    fn (Builder $weekly) => $weekly->where(
                        'academic_year_id',
                        $academicYearId
                    )
                )
            )
            ->orderByRaw($this->weekdayOrderSql())
            ->orderBy('school_bell_id');
    }

    private function baseEntriesQuery(int $subscriptionId): Builder
    {
        return TeacherTimetable::query()
            ->with([
                'teacher:id,name,email',
                'grade:id,name',
                'section:id,name',
                'stream:id,name',
                'subject:id,name',
                'bell',
                'timetableEntry.substituteTeacher:id,name,email',
            ])
            ->where('is_active', true)
            ->whereHas(
                'timetableEntry',
                fn (Builder $entry) => $entry->where('is_active', true)
            )
            ->whereHas(
                'timetableEntry.weeklyTimetable.template',
                fn (Builder $template) => $template->where(
                    'subscription_id',
                    $subscriptionId
                )
            );
    }

    private function bells(int $subscriptionId): Collection
    {
        return SchoolBell::query()
            ->where('subscription_id', $subscriptionId)
            ->where('is_active', true)
            ->where('is_teaching_period', true)
            ->orderBy('sort_order')
            ->get();
    }

    private function summary(
        Collection $entries,
        Collection $bells,
        int $dayCount = 6
    ): array {
        $totalSlots = $bells->count() * $dayCount;

        return [
            'weekly_periods' => $entries->count(),
            'free_periods' => max($totalSlots - $entries->count(), 0),
            'substitutions' => $entries
                ->filter(
                    fn ($entry) => (bool) (
                        $entry->timetableEntry?->is_substitution
                        ?? $entry['is_substitution']
                        ?? false
                    )
                )
                ->count(),
            'subjects' => $entries
                ->pluck('subject_id')
                ->filter()
                ->unique()
                ->count(),
        ];
    }

    private function entryRelations(): array
    {
        return [
            'weeklyTimetable.grade',
            'weeklyTimetable.section',
            'weeklyTimetable.stream',
            'weeklyTimetable.template',
            'teacher:id,name,email',
            'substituteTeacher:id,name,email',
            'subject:id,name',
            'bell',
        ];
    }

    private function transformEntry(TimetableEntry $entry): array
    {
        return [
            'id' => $entry->id,
            'timetable_entry_id' => $entry->id,
            'teacher_id' => $entry->teacher_id,
            'substitute_teacher_id' => $entry->substitute_teacher_id,
            'grade_id' => $entry->weeklyTimetable?->grade_id,
            'section_id' => $entry->weeklyTimetable?->section_id,
            'stream_id' => $entry->weeklyTimetable?->stream_id,
            'subject_id' => $entry->subject_id,
            'school_bell_id' => $entry->school_bell_id,
            'weekday' => $entry->weekday,
            'room_no' => $entry->room_no,
            'is_active' => (bool) $entry->is_active,
            'is_substitution' => (bool) $entry->is_substitution,
            'teacher' => $entry->teacher,
            'substitute_teacher' => $entry->substituteTeacher,
            'grade' => $entry->weeklyTimetable?->grade,
            'section' => $entry->weeklyTimetable?->section,
            'stream' => $entry->weeklyTimetable?->stream,
            'subject' => $entry->subject,
            'bell' => $entry->bell,
        ];
    }

    private function weekdayOrderSql(): string
    {
        return <<<'SQL'
CASE weekday
    WHEN 'Monday' THEN 1
    WHEN 'Tuesday' THEN 2
    WHEN 'Wednesday' THEN 3
    WHEN 'Thursday' THEN 4
    WHEN 'Friday' THEN 5
    WHEN 'Saturday' THEN 6
    ELSE 7
END
SQL;
    }
}