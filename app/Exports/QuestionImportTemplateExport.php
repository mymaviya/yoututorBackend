<?php

namespace App\Exports;

use App\Models\Subject;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;

class QuestionImportTemplateExport implements FromArray, WithHeadings, ShouldAutoSize
{
    public function headings(): array
    {
        return [
            'Grade',
            'Stream',
            'Subject',
            'Lesson',
            'Question Type',
            'Question',
            'Difficulty',
            'Bloom Level',
            'Marks',
            'Answer',
            'Explanation',
            'Options',
            'Correct Option',
            'Match Pairs',
        ];
    }

    public function array(): array
    {
        $subjects = Subject::with(['grade', 'stream'])
            ->orderBy('grade_id')
            ->orderBy('stream_id')
            ->orderBy('name')
            ->limit(20)
            ->get();

        if ($subjects->isEmpty()) {
            return [
                [
                    'Grade 10',
                    '',
                    'English',
                    'A Letter to God',
                    'mcq',
                    'Who is the author of A Letter to God?',
                    'medium',
                    'remember',
                    1,
                    'G. L. Fuentes',
                    'The lesson A Letter to God was written by G. L. Fuentes.',
                    'G. L. Fuentes|Ruskin Bond|R. K. Narayan|Premchand',
                    'A',
                    '',
                ],
            ];
        }

        return $subjects->map(function (Subject $subject) {
            return [
                $subject->grade?->name ?? '',
                $subject->stream?->name ?? '',
                $subject->name,
                '',
                'mcq',
                'Sample question text',
                'medium',
                'remember',
                1,
                'Sample answer',
                'Sample explanation for the answer.',
                'Option A|Option B|Option C|Option D',
                'A',
                '',
            ];
        })->values()->toArray();
    }
}
