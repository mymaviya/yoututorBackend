<?php

namespace App\Imports;

use App\Models\Grade;
use App\Models\Lesson;
use App\Models\MasterQuestion;
use App\Models\QuestionBankPackage;
use App\Models\QuestionTypeMaster;
use App\Models\Stream;
use App\Models\Subject;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;

class MasterQuestionImport implements ToCollection
{
    public int $imported = 0;
    public int $skipped = 0;
    public array $errors = [];

    private ?int $packageId;

    public function __construct(?int $packageId = null)
    {
        $this->packageId = $packageId;
    }

    public function collection(Collection $rows): void
    {
        foreach ($rows->skip(1) as $index => $row) {
            $line = $index + 1;

            try {
                $packageSlug = trim((string) ($row[0] ?? ''));
                $gradeName = trim((string) ($row[1] ?? ''));
                $streamName = trim((string) ($row[2] ?? ''));
                $subjectName = trim((string) ($row[3] ?? ''));
                $lessonName = trim((string) ($row[4] ?? ''));
                $questionType = trim((string) ($row[5] ?? ''));
                $question = trim((string) ($row[6] ?? ''));
                $difficulty = trim((string) ($row[7] ?? 'medium'));
                $bloomLevel = trim((string) ($row[8] ?? ''));
                $marks = (float) ($row[9] ?? 1);
                $answer = trim((string) ($row[10] ?? ''));
                $explanation = trim((string) ($row[11] ?? ''));
                $optionsText = trim((string) ($row[12] ?? ''));
                $correctOption = strtoupper(trim((string) ($row[13] ?? '')));
                $matchPairsText = trim((string) ($row[14] ?? ''));

                if (
                    (!$this->packageId && !$packageSlug) ||
                    !$gradeName ||
                    !$subjectName ||
                    !$questionType ||
                    !$question
                ) {
                    $this->skipped++;
                    $this->errors[] = "Row {$line}: Required fields missing.";
                    continue;
                }

                $package = $this->packageId
                    ? QuestionBankPackage::find($this->packageId)
                    : QuestionBankPackage::where('slug', $packageSlug)->first();

                if (!$package) {
                    $this->skipped++;
                    $this->errors[] = "Row {$line}: Package not found.";
                    continue;
                }

                $grade = Grade::where('name', $gradeName)->first();

                if (!$grade) {
                    $this->skipped++;
                    $this->errors[] = "Row {$line}: Grade not found.";
                    continue;
                }

                $stream = null;

                if ($streamName) {
                    $stream = Stream::where('name', $streamName)->first();

                    if (!$stream) {
                        $this->skipped++;
                        $this->errors[] = "Row {$line}: Stream not found.";
                        continue;
                    }
                }

                $subject = Subject::where('name', $subjectName)
                    ->where('grade_id', $grade->id)
                    ->when($stream, fn ($q) => $q->where('stream_id', $stream->id))
                    ->first();

                if (!$subject) {
                    $this->skipped++;
                    $this->errors[] = "Row {$line}: Subject not found.";
                    continue;
                }

                $lesson = null;

                if ($lessonName) {
                    $lesson = Lesson::firstOrCreate(
                        [
                            'grade_id' => $grade->id,
                            'stream_id' => $stream?->id,
                            'subject_id' => $subject->id,
                            'name' => $lessonName,
                        ],
                        [
                            'is_active' => true,
                        ]
                    );
                }

                $type = QuestionTypeMaster::where('slug', $questionType)
                    ->orWhere('name', $questionType)
                    ->first();

                if (!$type) {
                    $this->skipped++;
                    $this->errors[] = "Row {$line}: Question type not found.";
                    continue;
                }

                $exists = MasterQuestion::where('question_bank_package_id', $package->id)
                    ->where('grade_id', $grade->id)
                    ->where('stream_id', $stream?->id)
                    ->where('subject_id', $subject->id)
                    ->where('lesson_id', $lesson?->id)
                    ->where('question_type_master_id', $type->id)
                    ->where('question', $question)
                    ->exists();

                if ($exists) {
                    $this->skipped++;
                    continue;
                }

                DB::transaction(function () use (
                    $package,
                    $grade,
                    $stream,
                    $subject,
                    $lesson,
                    $type,
                    $question,
                    $difficulty,
                    $bloomLevel,
                    $marks,
                    $answer,
                    $explanation,
                    $optionsText,
                    $correctOption,
                    $matchPairsText
                ) {
                    $masterQuestion = MasterQuestion::create([
                        'question_bank_package_id' => $package->id,
                        'grade_id' => $grade->id,
                        'stream_id' => $stream?->id,
                        'subject_id' => $subject->id,
                        'lesson_id' => $lesson?->id,
                        'question_type_master_id' => $type->id,
                        'question' => $question,
                        'difficulty' => $difficulty ?: 'medium',
                        'bloom_level' => $bloomLevel ?: null,
                        'marks' => $marks ?: 1,
                        'answer' => $answer ?: null,
                        'explanation' => $explanation ?: null,
                        'language' => 'en',
                        'source' => 'platform',
                        'is_active' => true,
                    ]);

                    $this->createOptions($masterQuestion, $optionsText, $correctOption);
                    $this->createMatchPairs($masterQuestion, $matchPairsText);
                });

                $this->imported++;
            } catch (\Throwable $e) {
                $this->skipped++;
                $this->errors[] = "Row {$line}: " . $e->getMessage();
            }
        }
    }

    private function createOptions(MasterQuestion $question, string $optionsText, string $correctOption): void
    {
        if ($optionsText === '') {
            return;
        }

        $options = array_values(array_filter(array_map(
            fn ($value) => trim((string) $value),
            explode('|', $optionsText)
        )));

        foreach ($options as $index => $optionText) {
            $letter = chr(65 + $index);

            $question->options()->create([
                'option_text' => $optionText,
                'option_image' => null,
                'is_correct' => $correctOption === $letter || $correctOption === (string) ($index + 1),
                'sort_order' => $index,
            ]);
        }
    }

    private function createMatchPairs(MasterQuestion $question, string $matchPairsText): void
    {
        if ($matchPairsText === '') {
            return;
        }

        $pairs = array_values(array_filter(array_map(
            fn ($value) => trim((string) $value),
            explode('|', $matchPairsText)
        )));

        foreach ($pairs as $index => $pair) {
            if (!str_contains($pair, '=')) {
                $this->errors[] = "Match pair '{$pair}' is invalid. Use Left=Right format.";
                continue;
            }

            [$left, $right] = array_map('trim', explode('=', $pair, 2));

            if ($left === '' || $right === '') {
                continue;
            }

            $question->matchPairs()->create([
                'left_value' => $left,
                'right_value' => $right,
                'sort_order' => $index,
            ]);
        }
    }
}
