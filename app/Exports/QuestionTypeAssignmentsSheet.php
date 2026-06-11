<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithTitle;

class QuestionTypeAssignmentsSheet implements FromArray, WithTitle
{
    public function title(): string
    {
        return 'Question Type Assignments';
    }

    public function array(): array
    {
        return [
            ['Template Name', 'Grade', 'Stream', 'Subject'],
            ['CBSE English IX-X', 'Grade 9', '', 'English'],
            ['CBSE English IX-X', 'Grade 10', '', 'English'],
        ];
    }
}