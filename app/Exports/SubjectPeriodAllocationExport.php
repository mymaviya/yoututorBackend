<?php

namespace App\Exports;

use App\Models\SubjectPeriodAllocation;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithAutoFilter;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class SubjectPeriodAllocationExport implements
    FromQuery,
    WithMapping,
    WithHeadings,
    ShouldAutoSize,
    WithAutoFilter,
    WithStyles
{
    public function __construct(
        protected int $subscriptionId,
        protected int $gradeId,
        protected ?int $academicYearId = null,
        protected ?int $sectionId = null,
        protected ?int $streamId = null
    ) {}

    public function query(): Builder
    {
        return SubjectPeriodAllocation::query()
            ->with([
                'subject:id,name',
                'preferredTeacher:id,name',
            ])
            ->where('subscription_id', $this->subscriptionId)
            ->where('grade_id', $this->gradeId)
            ->where('academic_year_id', $this->academicYearId)
            ->where('section_id', $this->sectionId)
            ->where('stream_id', $this->streamId)
            ->orderBy('priority')
            ->orderBy('subject_id');
    }

    public function map($allocation): array
    {
        return [
            $allocation->subject?->name,
            $allocation->subject_category,
            $allocation->weekly_periods,
            $allocation->max_periods_per_day,
            $allocation->preferredTeacher?->name,
            $this->yesNo($allocation->prefer_double_period),
            $this->yesNo($allocation->prefer_morning),
            $this->yesNo($allocation->prefer_last_period),
            $this->yesNo($allocation->prefer_saturday),
            $this->yesNo($allocation->is_optional),
            $this->yesNo($allocation->is_parallel_subject),
            $allocation->parallel_group_code,
            $allocation->priority,
            $this->yesNo($allocation->is_active),
        ];
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
            'Last Period',
            'Saturday',
            'Optional',
            'Parallel',
            'Parallel Group Code',
            'Priority',
            'Active',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        $sheet->freezePane('A2');

        return [
            1 => ['font' => ['bold' => true]],
        ];
    }

    private function yesNo(bool $value): string
    {
        return $value ? 'Yes' : 'No';
    }
}
