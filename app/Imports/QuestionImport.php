<?php

namespace App\Imports;

use App\Models\Grade;
use App\Models\Lesson;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\QuestionTypeMaster;
use App\Models\Stream;
use App\Models\Subject;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\ToCollection;

class QuestionImport implements ToCollection
{
    public int $created = 0;
    public int $skipped = 0;
    public array $errors = [];

    public function __construct(
        private readonly ?int $createdBy = null
    ) {}

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
                $questionTypeText = trim((string) ($row[4] ?? ''));
                $questionText = trim((string) ($row[5] ?? ''));
                $difficulty = strtolower(trim((string) ($row[6] ?? 'medium')));
                $bloomLevel = strtolower(trim((string) ($row[7] ?? '')));
                $marks = (float) ($row[8] ?? 1);
                $answer = trim((string) ($row[9] ?? ''));
                $explanation = trim((string) ($row[10] ?? ''));
                $optionsText = trim((string) ($row[11] ?? ''));
                $correctOption = trim((string) ($row[12] ?? ''));
                $matchPairsText = trim((string) ($row[13] ?? ''));

                if (!$gradeName || !$subjectName || !$questionTypeText || !$questionText) {
                    $this->skipped++;
                    $this->errors[] = "Row {$rowNumber}: Grade, Subject, Question Type or Question missing.";
                    continue;
                }

                if (!in_array($difficulty, ['easy', 'medium', 'hard'])) {
                    $difficulty = 'medium';
                }

                if ($bloomLevel && !in_array($bloomLevel, ['remember', 'understand', 'apply', 'analyze', 'evaluate', 'create'])) {
                    $this->skipped++;
                    $this->errors[] = "Row {$rowNumber}: Invalid bloom level - {$bloomLevel}.";
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

                $lesson = null;

                if ($lessonName !== '') {
                    $lesson = Lesson::where('subject_id', $subject->id)
                        ->whereRaw('LOWER(name) = ?', [strtolower($lessonName)])
                        ->first();

                    if (!$lesson) {
                        $this->skipped++;
                        $this->errors[] = "Row {$rowNumber}: Lesson not found - {$lessonName}. Import lessons first.";
                        continue;
                    }
                }

                $questionType = $this->findQuestionType($questionTypeText);

                if (!$questionType) {
                    $this->skipped++;
                    $this->errors[] = "Row {$rowNumber}: Question type not found - {$questionTypeText}.";
                    continue;
                }

                $question = Question::create(array_merge($this->subscriptionPayload('questions'), [
                    'grade_id' => $grade->id,
                    'stream_id' => $subject->stream_id ?? $streamId,
                    'subject_id' => $subject->id,
                    'lesson_id' => $lesson?->id,
                    'question_type_master_id' => $questionType->id,
                    'question' => $questionText,
                    'difficulty' => $difficulty,
                    'bloom_level' => $bloomLevel ?: null,
                    'marks' => $marks ?: 1,
                    'answer' => $answer ?: null,
                    'explanation' => $explanation ?: null,
                    'status' => 'approved',
                    'approved_by' => $this->createdBy,
                    'approved_at' => now(),
                    'is_active' => true,
                    'created_by' => $this->createdBy,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]));

                $this->createOptions($question, $optionsText, $correctOption);
                $this->createMatchPairs($question, $matchPairsText);

                $this->created++;
            }
        });
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

    private function findQuestionType(string $value): ?QuestionTypeMaster
    {
        $normalized = strtolower(trim($value));
        $slug = Str::slug($value, '_');

        return QuestionTypeMaster::where('slug', $normalized)
            ->orWhere('slug', $slug)
            ->orWhereRaw('LOWER(name) = ?', [$normalized])
            ->first();
    }

    private function createOptions(Question $question, string $optionsText, string $correctOption): void
    {
        if ($optionsText === '') {
            return;
        }

        $options = array_values(array_filter(array_map('trim', preg_split('/\|/', $optionsText))));

        foreach ($options as $index => $optionText) {
            $label = chr(65 + $index);

            QuestionOption::create([
                'question_id' => $question->id,
                'option_text' => $optionText,
                'option_image' => null,
                'is_correct' => strtoupper($correctOption) === $label || strtolower($correctOption) === strtolower($optionText),
                'sort_order' => $index + 1,
            ]);
        }
    }

    private function createMatchPairs(Question $question, string $matchPairsText): void
    {
        if ($matchPairsText === '') {
            return;
        }

        $pairs = array_values(array_filter(array_map('trim', preg_split('/\|/', $matchPairsText))));

        foreach ($pairs as $index => $pairText) {
            $parts = array_map('trim', explode('=', $pairText, 2));

            if (count($parts) !== 2) {
                continue;
            }

            $question->matchPairs()->create([
                'left_value' => $parts[0],
                'right_value' => $parts[1],
                'sort_order' => $index + 1,
            ]);
        }
    }
}