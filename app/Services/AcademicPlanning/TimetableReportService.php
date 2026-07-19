<?php

namespace App\Services\AcademicPlanning;

use App\Models\TimetableEntry;
use App\Models\WeeklyTimetable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class TimetableReportService
{
    public function classReport(WeeklyTimetable $timetable): array
    {
        $entries = $this->entryQuery((int) $timetable->subscription_id)
            ->where('weekly_timetable_id', $timetable->id)
            ->get();

        return [
            'type' => 'class',
            'title' => $timetable->name,
            'timetable' => $timetable->load([
                'academicYear', 'grade', 'section', 'stream', 'template', 'publisher:id,name,email',
            ]),
            'rows' => $this->rows($entries),
            'summary' => $this->summary($entries),
        ];
    }

    public function teacherReport(
        int $subscriptionId,
        int $teacherId,
        ?int $academicYearId = null
    ): array {
        $entries = $this->entryQuery($subscriptionId)
            ->whereHas('weeklyTimetable', function (Builder $query) use ($academicYearId): void {
                $query->when(
                    $academicYearId !== null,
                    fn (Builder $scope) => $scope->where('academic_year_id', $academicYearId)
                );
            })
            ->where(function (Builder $query) use ($teacherId): void {
                $query->where(function (Builder $scope) use ($teacherId): void {
                    $scope->where('is_substitution', false)
                        ->where('teacher_id', $teacherId);
                })->orWhere(function (Builder $scope) use ($teacherId): void {
                    $scope->where('is_substitution', true)
                        ->where('substitute_teacher_id', $teacherId);
                });
            })
            ->get();

        $teacher = $entries->first()?->is_substitution
            ? $entries->first()?->substituteTeacher
            : $entries->first()?->teacher;

        return [
            'type' => 'teacher',
            'title' => $teacher?->name ?? 'Teacher Timetable',
            'teacher_id' => $teacherId,
            'academic_year_id' => $academicYearId,
            'rows' => $this->rows($entries),
            'summary' => $this->summary($entries),
        ];
    }

    public function roomReport(
        int $subscriptionId,
        int $roomId,
        ?int $academicYearId = null
    ): array {
        $entries = $this->entryQuery($subscriptionId)
            ->where('room_id', $roomId)
            ->whereHas('weeklyTimetable', function (Builder $query) use ($academicYearId): void {
                $query->when(
                    $academicYearId !== null,
                    fn (Builder $scope) => $scope->where('academic_year_id', $academicYearId)
                );
            })
            ->get();

        return [
            'type' => 'room',
            'title' => $entries->first()?->room?->name ?? 'Room Timetable',
            'room_id' => $roomId,
            'academic_year_id' => $academicYearId,
            'rows' => $this->rows($entries),
            'summary' => $this->summary($entries),
        ];
    }

    public function workload(
        int $subscriptionId,
        ?int $academicYearId = null
    ): array {
        $entries = $this->entryQuery($subscriptionId)
            ->whereHas('weeklyTimetable', function (Builder $query) use ($academicYearId): void {
                $query->when(
                    $academicYearId !== null,
                    fn (Builder $scope) => $scope->where('academic_year_id', $academicYearId)
                );
            })
            ->get();

        $teachers = $entries
            ->filter(fn (TimetableEntry $entry) => $entry->effectiveTeacherId() !== null)
            ->groupBy(fn (TimetableEntry $entry) => $entry->effectiveTeacherId())
            ->map(function (Collection $teacherEntries, int|string $teacherId): array {
                $first = $teacherEntries->first();
                $teacher = $first->is_substitution && $first->substitute_teacher_id === (int) $teacherId
                    ? $first->substituteTeacher
                    : $first->teacher;

                return [
                    'teacher_id' => (int) $teacherId,
                    'teacher_name' => $teacher?->name,
                    'weekly_periods' => $teacherEntries->count(),
                    'days' => $teacherEntries->groupBy('weekday')->map->count()->all(),
                    'classes' => $teacherEntries
                        ->pluck('weeklyTimetable.name')
                        ->filter()
                        ->unique()
                        ->values()
                        ->all(),
                    'substitution_periods' => $teacherEntries->where('is_substitution', true)->count(),
                ];
            })
            ->sortByDesc('weekly_periods')
            ->values()
            ->all();

        return [
            'academic_year_id' => $academicYearId,
            'teachers' => $teachers,
            'teacher_count' => count($teachers),
            'total_periods' => $entries->count(),
        ];
    }

    public function conflictReport(
        int $subscriptionId,
        ?int $academicYearId = null
    ): array {
        $entries = $this->entryQuery($subscriptionId)
            ->whereHas('weeklyTimetable', function (Builder $query) use ($academicYearId): void {
                $query->when(
                    $academicYearId !== null,
                    fn (Builder $scope) => $scope->where('academic_year_id', $academicYearId)
                );
            })
            ->get();

        $conflicts = collect();

        $entries
            ->filter(fn (TimetableEntry $entry) => $entry->effectiveTeacherId() !== null)
            ->groupBy(fn (TimetableEntry $entry) => implode(':', [
                $entry->effectiveTeacherId(), $entry->weekday, $entry->school_bell_id,
            ]))
            ->filter(fn (Collection $group) => $group->count() > 1)
            ->each(function (Collection $group) use ($conflicts): void {
                $first = $group->first();
                $conflicts->push([
                    'type' => 'teacher_double_booking',
                    'weekday' => (int) $first->weekday,
                    'school_bell_id' => (int) $first->school_bell_id,
                    'teacher_id' => $first->effectiveTeacherId(),
                    'room_id' => null,
                    'entry_ids' => $group->pluck('id')->all(),
                    'message' => 'A teacher is assigned to more than one class in the same period.',
                ]);
            });

        $entries
            ->whereNotNull('room_id')
            ->groupBy(fn (TimetableEntry $entry) => implode(':', [
                $entry->room_id, $entry->weekday, $entry->school_bell_id,
            ]))
            ->filter(fn (Collection $group) => $group->count() > 1)
            ->each(function (Collection $group) use ($conflicts): void {
                $first = $group->first();
                $conflicts->push([
                    'type' => 'room_double_booking',
                    'weekday' => (int) $first->weekday,
                    'school_bell_id' => (int) $first->school_bell_id,
                    'teacher_id' => null,
                    'room_id' => (int) $first->room_id,
                    'entry_ids' => $group->pluck('id')->all(),
                    'message' => 'A room is assigned to more than one class in the same period.',
                ]);
            });

        return [
            'academic_year_id' => $academicYearId,
            'conflict_count' => $conflicts->count(),
            'conflicts' => $conflicts->values()->all(),
        ];
    }

    private function entryQuery(int $subscriptionId): Builder
    {
        return TimetableEntry::query()
            ->active()
            ->forSubscription($subscriptionId)
            ->with([
                'weeklyTimetable:id,subscription_id,academic_year_id,name,grade_id,section_id,stream_id,status,version',
                'weeklyTimetable.grade:id,name',
                'weeklyTimetable.section:id,name',
                'weeklyTimetable.stream:id,name',
                'bell:id,title,start_time,end_time,period_number,sort_order',
                'teacher:id,name,email',
                'substituteTeacher:id,name,email',
                'subject:id,name',
                'lesson:id,name',
                'room:id,name,code,room_type',
                'parallelGroup:id,name',
            ])
            ->join('school_bells', 'school_bells.id', '=', 'timetable_entries.school_bell_id')
            ->select('timetable_entries.*')
            ->orderBy('timetable_entries.weekday')
            ->orderBy('school_bells.sort_order')
            ->orderBy('school_bells.start_time')
            ->orderBy('timetable_entries.id');
    }

    private function rows(Collection $entries): array
    {
        return $entries->map(function (TimetableEntry $entry): array {
            $timetable = $entry->weeklyTimetable;
            $teacher = $entry->is_substitution
                ? $entry->substituteTeacher
                : $entry->teacher;

            return [
                'entry_id' => $entry->id,
                'weekday' => (int) $entry->weekday,
                'day_name' => $this->dayName((int) $entry->weekday),
                'period_number' => $entry->bell?->period_number,
                'period_title' => $entry->bell?->display_title,
                'time' => $entry->bell?->display_time,
                'class_name' => $timetable?->name,
                'grade' => $timetable?->grade?->name,
                'section' => $timetable?->section?->name,
                'stream' => $timetable?->stream?->name,
                'subject' => $entry->subject?->name,
                'lesson' => $entry->lesson?->name,
                'teacher' => $teacher?->name,
                'is_substitution' => (bool) $entry->is_substitution,
                'room' => $entry->room?->code ?: ($entry->room?->name ?: $entry->room_no),
                'student_group' => $entry->student_group_name,
                'parallel_group' => $entry->parallelGroup?->name,
                'is_locked' => (bool) $entry->is_locked,
                'remarks' => $entry->remarks,
            ];
        })->all();
    }

    private function summary(Collection $entries): array
    {
        return [
            'total_periods' => $entries->count(),
            'teaching_days' => $entries->pluck('weekday')->unique()->count(),
            'subjects' => $entries->pluck('subject_id')->filter()->unique()->count(),
            'teachers' => $entries
                ->map(fn (TimetableEntry $entry) => $entry->effectiveTeacherId())
                ->filter()
                ->unique()
                ->count(),
            'rooms' => $entries->pluck('room_id')->filter()->unique()->count(),
            'locked_periods' => $entries->where('is_locked', true)->count(),
            'substitution_periods' => $entries->where('is_substitution', true)->count(),
        ];
    }

    private function dayName(int $weekday): string
    {
        return [
            1 => 'Monday',
            2 => 'Tuesday',
            3 => 'Wednesday',
            4 => 'Thursday',
            5 => 'Friday',
            6 => 'Saturday',
            7 => 'Sunday',
        ][$weekday] ?? 'Unknown';
    }
}
