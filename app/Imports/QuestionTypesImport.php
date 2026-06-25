<?php

namespace App\Imports;

use App\Models\Grade;
use App\Models\Subject;
use App\Models\Stream;
use App\Models\QuestionTypeMaster;
use App\Models\QuestionTypeAssignment;
use Illuminate\Support\Facades\Schema;
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

            $subject = $this->findSubject($grade->id, $streamId, $subjectName);

            if (!$subject) {
                $streamText = $streamName !== '' ? " ({$streamName})" : '';
                $this->skipped++;
                $this->errors[] = "Row {$rowNumber}: Subject not found - {$subjectName}{$streamText}. Apply Subject Template first.";
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
                array_merge($this->subscriptionPayload('question_type_assignments'), [
                    'question_type_master_id' => $master->id,
                    'grade_id' => $grade->id,
                    'stream_id' => $streamId,
                    'subject_id' => $subject->id,
                ]),
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


    private function subscriptionPayload(string $table): array
    {
        if (!auth()->check() || !Schema::hasColumn($table, 'subscription_id')) {
            return [];
        }

        return [
            'subscription_id' => auth()->user()->subscription_id,
        ];
    }

    private function findSubject(int $gradeId, ?int $streamId, string $subjectName): ?Subject
    {
        $normalized = strtolower(trim($subjectName));

        return Subject::where('grade_id', $gradeId)
            ->where(function ($q) use ($streamId) {
                $q->whereNull('stream_id');

                if ($streamId) {
                    $q->orWhere('stream_id', $streamId);
                }
            })
            ->where(function ($q) use ($normalized) {
                $q->whereRaw('LOWER(name) = ?', [$normalized])
                    ->orWhereRaw('LOWER(name) LIKE ?', ["%{$normalized}%"])
                    ->orWhereRaw('? LIKE CONCAT("%", LOWER(name), "%")', [$normalized]);
            })
            ->first();
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