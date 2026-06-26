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

                MasterQuestion::create([
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

                $this->imported++;
            } catch (\Throwable $e) {
                $this->skipped++;
                $this->errors[] = "Row {$line}: " . $e->getMessage();
            }
        }
    }
}
