<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;

class QuestionTypesTemplateExport implements FromArray, WithHeadings, ShouldAutoSize
{
    public function headings(): array
    {
        return [
            'Grade',
            'Subject',
            'Question Type',
        ];
    }

    public function array(): array
    {
        return [
            ['Class 1', 'English', 'MCQ'],
            ['Class 1', 'English', 'Fill in the blanks'],
            ['Class 2', 'English', 'Difficult Word'],
            ['Class 2', 'English', 'Make Sentences'],
            ['Class 2', 'English', 'MCQ'],
        ];
    }
}
