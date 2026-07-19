<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithAutoFilter;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class SubjectPeriodAllocationTemplateExport implements
    FromArray,
    WithHeadings,
    ShouldAutoSize,
    WithAutoFilter,
    WithStyles
{
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

    public function array(): array
    {
        return [
            ['English', 'major', 8, 2, '', 'Yes', 'Yes', 'No', 'No', 'No', 'No', '', 5, 'Yes'],
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        $sheet->freezePane('A2');

        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
