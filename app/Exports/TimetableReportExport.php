<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class TimetableReportExport implements FromArray, WithHeadings, WithMapping, ShouldAutoSize, WithStyles
{
    public function __construct(
        protected array $rows
    ) {}

    public function array(): array
    {
        return $this->rows;
    }

    public function headings(): array
    {
        return [
            'Day',
            'Period',
            'Time',
            'Class',
            'Grade',
            'Section',
            'Stream',
            'Subject',
            'Lesson',
            'Teacher',
            'Room',
            'Student Group',
            'Parallel Group',
            'Substitution',
            'Locked',
            'Remarks',
        ];
    }

    public function map($row): array
    {
        return [
            $row['day_name'] ?? '',
            $row['period_title'] ?? ($row['period_number'] ?? ''),
            $row['time'] ?? '',
            $row['class_name'] ?? '',
            $row['grade'] ?? '',
            $row['section'] ?? '',
            $row['stream'] ?? '',
            $row['subject'] ?? '',
            $row['lesson'] ?? '',
            $row['teacher'] ?? '',
            $row['room'] ?? '',
            $row['student_group'] ?? '',
            $row['parallel_group'] ?? '',
            ! empty($row['is_substitution']) ? 'Yes' : 'No',
            ! empty($row['is_locked']) ? 'Yes' : 'No',
            $row['remarks'] ?? '',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => ['bold' => true],
                'alignment' => ['horizontal' => 'center'],
            ],
        ];
    }
}
