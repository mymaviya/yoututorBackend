<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class SubjectPeriodAllocationTemplateExport implements FromArray, WithHeadings
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
            'Saturday',
            'Parallel',
            'Parallel Group Code',
            'Priority',
        ];
    }

    public function array(): array
    {
        return [
            ['English', 'major', 8, 2, '', 'Yes', 'Yes', 'No', 'No', '', 5],
        ];
    }
}