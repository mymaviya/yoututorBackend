<?php

namespace App\Exports;

use App\Models\Subject;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;

class QuestionTypesTemplateExport implements FromArray, WithHeadings, ShouldAutoSize
{
    public function headings(): array
    {
        return [
            'Grade',
            'Stream',
            'Subject',
            'Question Type',
        ];
    }

    public function array(): array
    {
        return Subject::with('grade')
            ->get()
            ->map(function ($subject) {
                return [
                    $subject->grade?->name,
                    $subject->grade?->stream ?? '',
                    $subject->name,
                    'MCQ',
                ];
            })
            ->toArray();
    }
}
