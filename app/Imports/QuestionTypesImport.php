<?php

namespace App\Imports;

use App\Models\Grade;
use App\Models\Subject;
use App\Models\Stream;
use App\Models\QuestionTypeMaster;
use App\Models\QuestionTypeAssignment;
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

            $gradeName = trim((string) ($row[0] ?? ''));
            $streamName = trim((string) ($row[1] ?? ''));
            $subjectName = trim((string) ($row[2] ?? ''));
            $typeName = trim((string) ($row[3] ?? ''));

            if (!$gradeName || !$subjectName || !$typeName) {
                $this->skipped++;
                $this->errors[] = "Row {$rowNumber}: Grade, Subject or Question Type missing.";
                continue;
            }

            $grade = Grade::whereRaw('LOWER(name) = ?', [strtolower($gradeName)])->first();

            if (!$grade) {
                $this->skipped++;
                $this->errors[] = "Row {$rowNumber}: Grade not found - {$gradeName}.";
                continue;
            }

            $streamId = null;

            if ($streamName !== '') {
                $stream = Stream::whereRaw('LOWER(name) = ?', [strtolower($streamName)])->first();

                if (!$stream) {
                    $this->skipped++;
                    $this->errors[] = "Row {$rowNumber}: Stream not found - {$streamName}.";
                    continue;
                }

                $streamId = $stream->id;
            }

            $subjectQuery = Subject::where('grade_id', $grade->id)
                ->whereRaw('LOWER(name) = ?', [strtolower($subjectName)]);

            if ($streamId) {
                $subjectQuery->where('stream_id', $streamId);
            } else {
                $subjectQuery->whereNull('stream_id');
            }

            $subject = $subjectQuery->first();

            if (!$subject) {
                $streamText = $streamName ? " ({$streamName})" : '';
                $this->skipped++;
                $this->errors[] = "Row {$rowNumber}: Subject not found - {$subjectName}{$streamText}.";
                continue;
            }

            $slug = Str::slug($typeName, '_');
            $defaults = $this->questionTypeDefaults($slug);

            $master = QuestionTypeMaster::firstOrCreate(
                ['slug' => $slug],
                array_merge([
                    'name' => $typeName,
                    'description' => null,
                    'is_active' => true,
                ], $defaults)
            );

            $assignment = QuestionTypeAssignment::firstOrCreate(
                [
                    'question_type_master_id' => $master->id,
                    'grade_id' => $grade->id,
                    'stream_id' => $streamId,
                    'subject_id' => $subject->id,
                ],
                [
                    'is_active' => true,
                ]
            );

            if ($assignment->wasRecentlyCreated) {
                $this->imported++;
            } else {
                $this->skipped++;
                $this->errors[] = "Row {$rowNumber}: Already assigned - {$typeName} for {$gradeName} / {$subjectName}.";
            }
        }
    }

    private function questionTypeDefaults(string $slug): array
    {
        return match ($slug) {
            'mcq', 'multiple_mcq', 'true_false' => [
                'has_options' => true,
                'has_answer' => true,
                'has_match_pairs' => false,
            ],
            'match_column' => [
                'has_options' => false,
                'has_answer' => true,
                'has_match_pairs' => true,
            ],
            default => [
                'has_options' => false,
                'has_answer' => true,
                'has_match_pairs' => false,
            ],
        };
    }
}
