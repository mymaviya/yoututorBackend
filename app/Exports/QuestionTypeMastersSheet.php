<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithTitle;

class QuestionTypeMastersSheet implements FromArray, WithTitle
{
    public function title(): string
    {
        return 'Question Type Masters';
    }

    public function array(): array
    {
        return [
            ['Name', 'Slug'],
            ['MCQ', 'mcq'],
            ['Short Answer', 'short'],
            ['Long Answer', 'long'],
            ['Extract Based', 'extract_based'],
            ['Formal Letter', 'formal_letter'],
            ['Analytical Paragraph', 'analytical_paragraph'],
        ];
    }
}