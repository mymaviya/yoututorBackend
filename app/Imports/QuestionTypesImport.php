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
    public int $imported = 0;
    public int $skipped = 0;
    public array $errors = [];

    public function collection(Collection $rows)
    {
        $rows->shift();

        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2;

            $gradeName = trim($row[0] ?? '');
            $stream = trim($row[1] ?? '');
            $subjectName = trim($row[2] ?? '');
            $typeName = trim($row[3] ?? '');

            if (!$gradeName || !$subjectName || !$typeName) {
                $this->skipped++;
                $this->errors[] = "Row {$rowNumber}: Grade, Subject or Question Type missing.";
                continue;
            }

            $gradeQuery = Grade::whereRaw(
                'LOWER(name) = ?',
                [strtolower($gradeName)]
            );

            if ($stream !== '') {
                $gradeQuery->whereRaw(
                    'LOWER(stream) = ?',
                    [strtolower($stream)]
                );
            }

            $grade = $gradeQuery->first();

            if (!$grade) {
                $this->skipped++;
                $this->errors[] = "Row {$rowNumber}: Grade not found - {$gradeName} {$stream}.";
                continue;
            }

            $subject = Subject::where('grade_id', $grade->id)
                ->whereRaw('LOWER(name) = ?', [strtolower($subjectName)])
                ->first();

            if (!$subject) {
                $this->skipped++;
                $this->errors[] = "Row {$rowNumber}: Subject not found - {$subjectName}.";
                continue;
            }

            $questionType = QuestionType::firstOrCreate(
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

            if ($questionType->wasRecentlyCreated) {
                $this->imported++;
            } else {
                $this->skipped++;
                $this->errors[] = "Row {$rowNumber}: Already exists - {$typeName}.";
            }
        }
    }
}
