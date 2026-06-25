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
        return Subject::with(['grade', 'stream'])
            ->orderBy('grade_id')
            ->orderBy('stream_id')
            ->orderBy('name')
            ->get()
            ->map(function (Subject $subject) {
                return [
                    $subject->grade?->name ?? '',
                    $subject->stream?->name ?? '',
                    $subject->name,
                    'MCQ',
                ];
            })
            ->values()
            ->toArray();
    }
}
