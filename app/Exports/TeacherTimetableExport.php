<?php

namespace App\Exports;

use App\Models\TeacherTimetable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class TeacherTimetableExport implements
    FromCollection,
    WithHeadings,
    WithMapping,
    ShouldAutoSize
{
    public function __construct(
        protected int $subscriptionId,
        protected ?int $teacherId = null,
        protected ?int $academicYearId = null,
        protected ?int $gradeId = null,
        protected ?int $sectionId = null,
        protected ?int $streamId = null,
    ) {}

    public function collection(): Collection
    {
        return TeacherTimetable::query()
            ->with([
                'teacher:id,name,employee_code',
                'grade:id,name',
                'section:id,name',
                'stream:id,name',
                'subject:id,name',
                'bell:id,title,start_time,end_time',
                'timetableEntry:id,weekly_timetable_id,substitute_teacher_id,is_substitution,is_active',
                'timetableEntry.substituteTeacher:id,name,employee_code',
            ])
            ->active()
            ->forSubscription($this->subscriptionId)
            ->whereHas(
                'timetableEntry',
                fn (Builder $entry) => $entry->where('is_active', true)
            )
            ->when(
                $this->teacherId !== null,
                fn (Builder $query) => $query->forTeacher(
                    $this->teacherId,
                    true
                )
            )
            ->when(
                $this->gradeId !== null,
                fn (Builder $query) => $query->forClass(
                    $this->gradeId,
                    $this->sectionId,
                    $this->streamId
                )
            )
            ->forAcademicYear($this->academicYearId)
            ->orderByRaw(<<<'SQL'
                CASE LOWER(weekday)
                    WHEN 'monday' THEN 1
                    WHEN 'tuesday' THEN 2
                    WHEN 'wednesday' THEN 3
                    WHEN 'thursday' THEN 4
                    WHEN 'friday' THEN 5
                    WHEN 'saturday' THEN 6
                    ELSE 7
                END
                SQL)
            ->orderBy('school_bell_id')
            ->get();
    }

    public function headings(): array
    {
        return [
            'Teacher',
            'Employee Code',
            'Weekday',
            'Period',
            'Subject',
            'Grade',
            'Section',
            'Stream',
            'Room',
            'Substitution',
            'Original Teacher',
        ];
    }

    public function map($row): array
    {
        $isSubstitution = (bool) $row->timetableEntry?->is_substitution;
        $effectiveTeacher = $isSubstitution
            ? $row->timetableEntry?->substituteTeacher
            : $row->teacher;

        return [
            $effectiveTeacher?->name,
            $effectiveTeacher?->employee_code,
            $row->weekday,
            $row->bell?->title,
            $row->subject?->name,
            $row->grade?->name,
            $row->section?->name,
            $row->stream?->name,
            $row->room_no,
            $isSubstitution ? 'Yes' : 'No',
            $isSubstitution ? $row->teacher?->name : null,
        ];
    }
}
