<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;

class TeacherImportTemplateExport implements FromArray
{
    public function array(): array
    {
        return [
            [
                'Name',
                'Email',
                'Mobile',
                'Qualification',
                'Address',
            ],
            [
                'Mudassir Husain',
                'mudassir@example.com',
                '9876543210',
                'M.Sc, B.Ed',
                'Siddharth Nagar',
            ],
        ];
    }
}
