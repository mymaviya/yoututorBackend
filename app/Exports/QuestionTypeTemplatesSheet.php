<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithTitle;

class QuestionTypeTemplatesSheet implements FromArray, WithTitle
{
    public function title(): string
    {
        return 'Question Type Templates';
    }

    public function array(): array
    {
        return [
            ['Template Name', 'Category', 'Question Type Slug'],
            ['CBSE English IX-X', 'CBSE English', 'mcq'],
            ['CBSE English IX-X', 'CBSE English', 'short'],
            ['CBSE English IX-X', 'CBSE English', 'long'],
            ['CBSE English IX-X', 'CBSE English', 'extract_based'],
            ['CBSE English IX-X', 'CBSE English', 'formal_letter'],
            ['CBSE English IX-X', 'CBSE English', 'analytical_paragraph'],
        ];
    }
}