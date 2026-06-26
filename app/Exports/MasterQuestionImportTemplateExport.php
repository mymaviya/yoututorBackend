<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;

class MasterQuestionImportTemplateExport implements FromArray
{
    public function array(): array
    {
        return [
            [
                'Package Slug',
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
            ],
            [
                'grade-6-8-science',
                'Grade 6',
                '',
                'Science',
                'Food: Where Does It Come From?',
                'mcq',
                'Which part of the plant is carrot?',
                'easy',
                'remember',
                '1',
                'Root',
                'Carrot is an edible root.',
            ],
            [
                '',
                'Grade 7',
                '',
                'Mathematics',
                'Integers',
                'short',
                'Find the value of -8 + 15.',
                'easy',
                'apply',
                '2',
                '7',
                'Adding 15 to -8 moves 15 steps right on the number line, giving 7.',
            ],
            [
                'grade-11-12-science',
                'Grade 11',
                'Science',
                'Physics',
                'Motion in a Straight Line',
                'mcq',
                'What is the SI unit of acceleration?',
                'easy',
                'remember',
                '1',
                'm/s²',
                'Acceleration is the rate of change of velocity with respect to time.',
            ],
            [
                'NOTE',
                'If you select a package from the import page, Package Slug can be blank.',
                'For Grade 11-12, Stream is recommended.',
                'Subject and Lesson names must already exist, or Lesson will be created by import if supported.',
                'Use question type slug or name, for example mcq, short, long, match_column.',
                'Required columns: Grade, Subject, Question Type, Question.',
                '',
                'Difficulty examples: easy, medium, hard.',
                'Bloom examples: remember, understand, apply, analyze, evaluate, create.',
                '',
                '',
                '',
            ],
        ];
    }
}