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
        protected ?int $teacherId = null,
        protected ?int $academicYearId = null,
        protected ?int $gradeId = null,
        protected ?int $sectionId = null,
        protected ?int $streamId = null,
        protected ?int $subscriptionId = null,
    ) {}

    public function collection(): Collection
    {
        return TeacherTimetable::query()
            ->with([
                'teacher',
                'grade',
                'section',
                'stream',
                'subject',
                'bell',
                'timetableEntry.substituteTeacher',
            ])
            ->when(
                $this->teacherId,
                fn (Builder $query) => $query->where('teacher_id', $this->teacherId)
            )
            ->when(
                $this->gradeId,
                fn (Builder $query) => $query->where('grade_id', $this->gradeId)
            )
            ->when(
                $this->sectionId,
                fn (Builder $query) => $query->where('section_id', $this->sectionId)
            )
            ->when(
                $this->streamId,
                fn (Builder $query) => $query->where('stream_id', $this->streamId)
            )
            ->when(
                $this->academicYearId,
                fn (Builder $query) => $query->whereHas(
                    'timetableEntry.weeklyTimetable',
                    fn (Builder $weekly) => $weekly->where(
                        'academic_year_id',
                        $this->academicYearId
                    )
                )
            )
            ->when(
                $this->subscriptionId,
                fn (Builder $query) => $query->whereHas(
                    'timetableEntry.weeklyTimetable.template',
                    fn (Builder $template) => $template->where(
                        'subscription_id',
                        $this->subscriptionId
                    )
                )
            )
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
            'Substitute Teacher',
        ];
    }

    public function map($row): array
    {
        return [
            $row->teacher?->name,
            $row->teacher?->employee_code,
            $row->weekday,
            $row->bell?->title,
            $row->subject?->name,
            $row->grade?->name,
            $row->section?->name,
            $row->stream?->name,
            $row->room_no,
            $row->timetableEntry?->is_substitution ? 'Yes' : 'No',
            $row->timetableEntry?->substituteTeacher?->name,
        ];
    }
}