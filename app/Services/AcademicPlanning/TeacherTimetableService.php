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
    public function teacherTimetable(
        int $teacherId,
        ?int $academicYearId = null,
        ?int $subscriptionId = null
    ): array {
        $teacher = User::query()
            ->when($subscriptionId, fn(Builder $query) => $query->where('subscription_id', $subscriptionId))
            ->findOrFail($teacherId);

        $entries = $this->teacherEntriesQuery($teacherId, $academicYearId, $subscriptionId)->get();

        return [
            'teacher' => $teacher,
            'bells' => $this->bells(),
            'entries' => $entries,
            'summary' => $this->summary($entries),
        ];
    }

    public function classTimetable(
        int $gradeId,
        ?int $sectionId = null,
        ?int $streamId = null,
        ?int $academicYearId = null,
        ?int $subscriptionId = null
    ): array {
        $entries = $this->classEntriesQuery(
            $gradeId,
            $sectionId,
            $streamId,
            $academicYearId,
            $subscriptionId
        )->get();

        return [
            'bells' => $this->bells(),
            'entries' => $entries,
            'summary' => $this->summary($entries),
        ];
    }

    public function today(
        ?int $teacherId = null,
        ?int $gradeId = null,
        ?int $sectionId = null,
        ?int $streamId = null,
        ?int $academicYearId = null,
        ?int $subscriptionId = null
    ): array {
        $weekday = now()->format('l');

        $entries = TimetableEntry::query()
            ->with($this->entryRelations())
            ->where('weekday', $weekday)
            ->where('is_active', true)
            ->when($teacherId, fn(Builder $q) => $q->where('teacher_id', $teacherId))
            ->when($gradeId, fn(Builder $q) => $q->whereHas('weeklyTimetable', fn(Builder $w) => $w->where('grade_id', $gradeId)))
            ->when($sectionId, fn(Builder $q) => $q->whereHas('weeklyTimetable', fn(Builder $w) => $w->where('section_id', $sectionId)))
            ->when($streamId, fn(Builder $q) => $q->whereHas('weeklyTimetable', fn(Builder $w) => $w->where('stream_id', $streamId)))
            ->when($academicYearId, fn(Builder $q) => $q->whereHas('weeklyTimetable', fn(Builder $w) => $w->where('academic_year_id', $academicYearId)))
            ->when($subscriptionId, fn(Builder $q) => $q->whereHas('weeklyTimetable.template', fn(Builder $t) => $t->where('subscription_id', $subscriptionId)))
            ->orderBy('school_bell_id')
            ->get()
            ->map(fn(TimetableEntry $entry) => $this->transformEntry($entry));

        return [
            'weekday' => $weekday,
            'bells' => $this->bells(),
            'entries' => $entries,
            'summary' => $this->summary($entries),
        ];
    }

    public function freePeriods(
        int $teacherId,
        ?int $academicYearId = null,
        ?int $subscriptionId = null
    ): array {
        $bells = $this->bells();
        $entries = $this->teacherEntriesQuery($teacherId, $academicYearId, $subscriptionId)->get();
        $weekdays = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
        $freePeriods = [];

        foreach ($weekdays as $weekday) {
            $busyBellIds = $entries
                ->where('weekday', $weekday)
                ->pluck('school_bell_id')
                ->map(fn($id) => (int) $id)
                ->all();

            $freePeriods[$weekday] = $bells
                ->reject(fn($bell) => in_array((int) $bell->id, $busyBellIds, true))
                ->values();
        }

        return [
            'teacher_id' => $teacherId,
            'free_periods' => $freePeriods,
        ];
    }

    public function workload(
        int $teacherId,
        ?int $academicYearId = null,
        ?int $subscriptionId = null
    ): array {
        $entries = $this->teacherEntriesQuery($teacherId, $academicYearId, $subscriptionId)->get();

        return [
            'teacher_id' => $teacherId,
            'weekly_periods' => $entries->count(),
            'daily_load' => $entries->groupBy('weekday')->map(fn(Collection $rows) => $rows->count()),
            'subjects' => $entries->pluck('subject_id')->filter()->unique()->count(),
            'substitutions' => $entries->filter(fn($entry) => (bool) ($entry->timetableEntry?->is_substitution ?? false))->count(),
        ];
    }

    private function teacherEntriesQuery(
        int $teacherId,
        ?int $academicYearId,
        ?int $subscriptionId
    ): Builder {
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
            ->where('teacher_id', $teacherId)
            ->where('is_active', true)
            ->when($academicYearId, fn(Builder $query) => $query->whereHas('timetableEntry.weeklyTimetable', fn(Builder $weekly) => $weekly->where('academic_year_id', $academicYearId)))
            ->when($subscriptionId, fn(Builder $query) => $query->whereHas('timetableEntry.weeklyTimetable.template', fn(Builder $template) => $template->where('subscription_id', $subscriptionId)))
            ->orderByRaw("
CASE weekday
    WHEN 'Monday' THEN 1
    WHEN 'Tuesday' THEN 2
    WHEN 'Wednesday' THEN 3
    WHEN 'Thursday' THEN 4
    WHEN 'Friday' THEN 5
    WHEN 'Saturday' THEN 6
    ELSE 7
END
")
            ->orderBy('school_bell_id');
    }

    private function classEntriesQuery(
        int $gradeId,
        ?int $sectionId,
        ?int $streamId,
        ?int $academicYearId,
        ?int $subscriptionId
    ): Builder {
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
            ->where('grade_id', $gradeId)
            ->where('is_active', true)
            ->when($sectionId, fn(Builder $query) => $query->where('section_id', $sectionId))
            ->when($streamId, fn(Builder $query) => $query->where('stream_id', $streamId))
            ->when($academicYearId, fn(Builder $query) => $query->whereHas('timetableEntry.weeklyTimetable', fn(Builder $weekly) => $weekly->where('academic_year_id', $academicYearId)))
            ->when($subscriptionId, fn(Builder $query) => $query->whereHas('timetableEntry.weeklyTimetable.template', fn(Builder $template) => $template->where('subscription_id', $subscriptionId)))
            ->orderByRaw("
CASE weekday
    WHEN 'Monday' THEN 1
    WHEN 'Tuesday' THEN 2
    WHEN 'Wednesday' THEN 3
    WHEN 'Thursday' THEN 4
    WHEN 'Friday' THEN 5
    WHEN 'Saturday' THEN 6
    ELSE 7
END
")
            ->orderBy('school_bell_id');
    }

    private function bells(): Collection
    {
        return SchoolBell::query()
            ->where('is_active', true)
            ->where('is_teaching_period', true)
            ->orderBy('sort_order')
            ->get();
    }

    private function summary(Collection $entries): array
    {
        $totalSlots = $this->bells()->count() * 6;

        return [
            'weekly_periods' => $entries->count(),
            'free_periods' => max($totalSlots - $entries->count(), 0),
            'substitutions' => $entries->filter(fn($entry) => (bool) ($entry->timetableEntry?->is_substitution ?? $entry['is_substitution'] ?? false))->count(),
            'subjects' => $entries->pluck('subject_id')->filter()->unique()->count(),
        ];
    }

    private function entryRelations(): array
    {
        return [
            'weeklyTimetable.grade',
            'weeklyTimetable.section',
            'weeklyTimetable.stream',
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
}
