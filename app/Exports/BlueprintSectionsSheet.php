<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithTitle;

class BlueprintSectionsSheet implements FromArray, WithTitle
{
    public function title(): string
    {
        return 'Blueprint Sections';
    }

    public function array(): array
    {
        return [
            ['Blueprint Name', 'Section Name', 'Question Type Slug', 'Difficulty', 'Question Count', 'Marks Per Question', 'Instructions', 'Sort Order'],

            ['CBSE Class 10 English Board Pattern', 'Section A - Reading', 'mcq', 'medium', 10, 1, 'Discursive passage / case-based passage', 1],
            ['CBSE Class 10 English Board Pattern', 'Section A - Reading', 'short', 'medium', 5, 2, 'Short answer comprehension questions', 2],

            ['CBSE Class 10 English Board Pattern', 'Section B - Grammar', 'mcq', 'medium', 10, 1, 'Grammar gap filling/editing/transformation', 3],
            ['CBSE Class 10 English Board Pattern', 'Section B - Writing', 'formal_letter', 'medium', 1, 5, 'Formal letter, one out of two', 4],
            ['CBSE Class 10 English Board Pattern', 'Section B - Writing', 'analytical_paragraph', 'medium', 1, 5, 'Analytical paragraph, one out of two', 5],

            ['CBSE Class 10 English Board Pattern', 'Section C - Literature', 'extract_based', 'medium', 2, 5, 'Drama/prose and poetry extracts', 6],
            ['CBSE Class 10 English Board Pattern', 'Section C - Literature', 'short', 'medium', 6, 3, 'Short answer questions from literature', 7],
            ['CBSE Class 10 English Board Pattern', 'Section C - Literature', 'long', 'medium', 2, 6, 'Long answer questions from prescribed books', 8],
        ];
    }
}