<?php

namespace App\Imports;

use App\Models\Grade;
use App\Models\Lesson;
use App\Models\Stream;
use App\Models\Subject;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;

class LessonImport implements ToCollection
{
    public int $created = 0;
    public int $skipped = 0;
    public array $errors = [];

    public function collection(Collection $rows)
    {
        $rows->shift();

        DB::transaction(function () use ($rows) {
            foreach ($rows as $index => $row) {
                $rowNumber = $index + 2;

                $gradeName = trim((string) ($row[0] ?? ''));
                $streamName = trim((string) ($row[1] ?? ''));
                $subjectName = trim((string) ($row[2] ?? ''));
                $lessonName = trim((string) ($row[3] ?? ''));
                $genre = trim((string) ($row[4] ?? ''));

                if (!$gradeName || !$subjectName || !$lessonName) {
                    $this->skipped++;
                    $this->errors[] = "Row {$rowNumber}: Grade, Subject or Lesson missing.";
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

                $lessonStreamId = $subject->stream_id ?? $streamId;

                $exists = Lesson::where('subject_id', $subject->id)
                    ->whereRaw('LOWER(name) = ?', [strtolower($lessonName)])
                    ->exists();

                if ($exists) {
                    $this->skipped++;
                    continue;
                }

                Lesson::create([
                    'grade_id' => $grade->id,
                    'stream_id' => $lessonStreamId,
                    'subject_id' => $subject->id,
                    'name' => $lessonName,
                    'genre' => $genre ?: null,
                    'is_active' => true,
                ]);

                $this->created++;
            }
        });
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
}