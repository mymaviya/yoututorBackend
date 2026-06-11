<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithTitle;

class PaperBlueprintsSheet implements FromArray, WithTitle
{
    public function title(): string
    {
        return 'Paper Blueprints';
    }

    public function array(): array
    {
        return [
            ['Blueprint Name', 'Grade', 'Stream', 'Subject', 'Exam Name', 'Duration Minutes', 'Total Marks'],
            ['CBSE Class 10 English Board Pattern', 'Grade 10', '', 'English', 'Annual Examination', 180, 80],
        ];
    }
}