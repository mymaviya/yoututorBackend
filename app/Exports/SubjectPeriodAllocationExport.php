<?php

namespace App\Exports;

use App\Models\SubjectPeriodAllocation;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class SubjectPeriodAllocationExport implements FromCollection, WithHeadings
{
    public function __construct(
        protected int $subscriptionId,
        protected int $gradeId,
        protected ?int $academicYearId = null,
        protected ?int $sectionId = null,
        protected ?int $streamId = null
    ) {}

    public function collection()
    {
        return SubjectPeriodAllocation::with(['subject', 'preferredTeacher'])
            ->where('subscription_id', $this->subscriptionId)
            ->where('grade_id', $this->gradeId)
            ->where('academic_year_id', $this->academicYearId)
            ->where('section_id', $this->sectionId)
            ->where('stream_id', $this->streamId)
            ->get()
            ->map(fn ($row) => [
                'subject' => $row->subject?->name,
                'category' => $row->subject_category,
                'weekly_periods' => $row->weekly_periods,
                'max_periods_per_day' => $row->max_periods_per_day,
                'preferred_teacher' => $row->preferredTeacher?->name,
                'double_period' => $row->prefer_double_period ? 'Yes' : 'No',
                'morning' => $row->prefer_morning ? 'Yes' : 'No',
                'saturday' => $row->prefer_saturday ? 'Yes' : 'No',
                'parallel' => $row->is_parallel_subject ? 'Yes' : 'No',
                'parallel_group_code' => $row->parallel_group_code,
                'priority' => $row->priority,
            ]);
    }

    public function headings(): array
    {
        return [
            'Subject',
            'Category',
            'Weekly Periods',
            'Max Periods Per Day',
            'Preferred Teacher',
            'Double Period',
            'Morning',
            'Saturday',
            'Parallel',
            'Parallel Group Code',
            'Priority',
        ];
    }
}