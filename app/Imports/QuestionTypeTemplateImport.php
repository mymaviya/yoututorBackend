<?php

namespace App\Imports;

use App\Models\Grade;
use App\Models\QuestionTypeAssignment;
use App\Models\QuestionTypeMaster;
use App\Models\QuestionTypeTemplate;
use App\Models\Stream;
use App\Models\Subject;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Facades\Excel;

class QuestionTypeTemplateImport implements ToCollection
{
    public int $created = 0;
    public int $skipped = 0;
    public array $errors = [];

    public function collection(Collection $rows)
    {
        // This class will be called sheet-wise from controller.
    }

    public function import(string $filePath): array
    {
        $sheets = Excel::toArray([], $filePath);

        DB::transaction(function () use ($sheets) {
            $this->importMasters($sheets[0] ?? []);
            $this->importTemplates($sheets[1] ?? []);
            $this->importAssignments($sheets[2] ?? []);
        });

        return [
            'created' => $this->created,
            'skipped' => $this->skipped,
            'errors' => $this->errors,
        ];
    }

    private function importMasters(array $rows): void
    {
        foreach (array_slice($rows, 1) as $index => $row) {
            $rowNumber = $index + 2;

            $name = trim($row[0] ?? '');
            $slug = trim($row[1] ?? '');

            if (!$name) {
                $this->skipped++;
                $this->errors[] = "Question Type Masters Row {$rowNumber}: Name missing.";
                continue;
            }

            $slug = $slug ?: Str::slug($name, '_');

            $master = QuestionTypeMaster::firstOrCreate(
                ['slug' => $slug],
                [
                    'name' => $name,
                    'is_active' => true,
                ]
            );

            $master->wasRecentlyCreated ? $this->created++ : $this->skipped++;
        }
    }

    private function importTemplates(array $rows): void
    {
        foreach (array_slice($rows, 1) as $index => $row) {
            $rowNumber = $index + 2;

            $templateName = trim($row[0] ?? '');
            $category = trim($row[1] ?? '');
            $questionTypeSlug = trim($row[2] ?? '');

            if (!$templateName || !$questionTypeSlug) {
                $this->skipped++;
                $this->errors[] = "Question Type Templates Row {$rowNumber}: Template or question type missing.";
                continue;
            }

            $master = QuestionTypeMaster::where('slug', $questionTypeSlug)->first();

            if (!$master) {
                $this->skipped++;
                $this->errors[] = "Question Type Templates Row {$rowNumber}: Question type not found - {$questionTypeSlug}.";
                continue;
            }

            $template = QuestionTypeTemplate::firstOrCreate(
                ['name' => $templateName],
                [
                    'category' => $category ?: null,
                    'is_active' => true,
                ]
            );

            $exists = $template->items()
                ->where('question_type_master_id', $master->id)
                ->exists();

            if ($exists) {
                $this->skipped++;
                continue;
            }

            $template->items()->create([
                'question_type_master_id' => $master->id,
            ]);

            $this->created++;
        }
    }

    private function importAssignments(array $rows): void
    {
        foreach (array_slice($rows, 1) as $index => $row) {
            $rowNumber = $index + 2;

            $templateName = trim($row[0] ?? '');
            $gradeName = trim($row[1] ?? '');
            $streamName = trim($row[2] ?? '');
            $subjectName = trim($row[3] ?? '');

            if (!$templateName || !$gradeName || !$subjectName) {
                $this->skipped++;
                $this->errors[] = "Question Type Assignments Row {$rowNumber}: Template, grade or subject missing.";
                continue;
            }

            $template = QuestionTypeTemplate::with('items')
                ->where('name', $templateName)
                ->first();

            if (!$template) {
                $this->skipped++;
                $this->errors[] = "Question Type Assignments Row {$rowNumber}: Template not found - {$templateName}.";
                continue;
            }

            $grade = Grade::whereRaw('LOWER(name) = ?', [strtolower($gradeName)])
                ->first();

            if (!$grade) {
                $this->skipped++;
                $this->errors[] = "Question Type Assignments Row {$rowNumber}: Grade not found - {$gradeName}.";
                continue;
            }

            $streamId = null;

            if ($streamName) {
                $stream = Stream::whereRaw('LOWER(name) = ?', [strtolower($streamName)])
                    ->first();

                if (!$stream) {
                    $this->skipped++;
                    $this->errors[] = "Question Type Assignments Row {$rowNumber}: Stream not found - {$streamName}.";
                    continue;
                }

                $streamId = $stream->id;
            }

            $subject = $this->findOrCreateSubject(
                $grade->id,
                $streamId,
                $subjectName
            );

            foreach ($template->items as $item) {
                $exists = QuestionTypeAssignment::where('grade_id', $grade->id)
                    ->where('stream_id', $streamId)
                    ->where('subject_id', $subject->id)
                    ->where('question_type_master_id', $item->question_type_master_id)
                    ->exists();

                if ($exists) {
                    $this->skipped++;
                    continue;
                }

                QuestionTypeAssignment::create([
                    'grade_id' => $grade->id,
                    'stream_id' => $streamId,
                    'subject_id' => $subject->id,
                    'question_type_master_id' => $item->question_type_master_id,
                    'is_active' => true,
                ]);

                $this->created++;
            }
        }
    }

    private function findOrCreateSubject($gradeId, $streamId, $subjectName)
    {
        $normalized = strtolower(trim($subjectName));

        $subject = Subject::where('grade_id', $gradeId)
            ->where('stream_id', $streamId)
            ->where(function ($q) use ($normalized) {
                $q->whereRaw('LOWER(name) = ?', [$normalized])
                    ->orWhereRaw('LOWER(name) LIKE ?', ["%{$normalized}%"])
                    ->orWhereRaw('? LIKE CONCAT("%", LOWER(name), "%")', [$normalized]);
            })
            ->first();

        if ($subject) {
            return $subject;
        }

        return Subject::create([
            'grade_id' => $gradeId,
            'stream_id' => $streamId,
            'name' => trim($subjectName),
            'is_active' => true,
        ]);
    }
}
