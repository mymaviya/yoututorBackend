<?php

namespace App\Imports;

use App\Models\Grade;
use App\Models\Subject;
use App\Models\QuestionType;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\ToCollection;
use Illuminate\Support\Collection;

class QuestionTypesImport implements ToCollection
{
    public function collection(Collection $rows)
    {
        $rows->shift(); // remove heading row

        foreach ($rows as $row) {
            $gradeName = trim($row[0] ?? '');
            $subjectName = trim($row[1] ?? '');
            $typeName = trim($row[2] ?? '');

            if (!$gradeName || !$subjectName || !$typeName) {
                continue;
            }

            $grade = Grade::where('name', $gradeName)->first();

            if (!$grade) {
                continue;
            }

            $subject = Subject::where('grade_id', $grade->id)
                ->where('name', $subjectName)
                ->first();

            if (!$subject) {
                continue;
            }

            QuestionType::firstOrCreate(
                [
                    'grade_id' => $grade->id,
                    'subject_id' => $subject->id,
                    'name' => $typeName,
                ],
                [
                    'slug' => Str::slug($typeName, '_'),
                    'is_active' => true,
                ]
            );
        }
    }
}
