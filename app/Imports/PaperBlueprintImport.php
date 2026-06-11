<?php

namespace App\Imports;

use App\Models\ExamName;
use App\Models\Grade;
use App\Models\PaperBlueprint;
use App\Models\QuestionTypeMaster;
use App\Models\Stream;
use App\Models\Subject;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Collection;

class PaperBlueprintImport implements ToCollection
{
    public int $created = 0;
    public int $skipped = 0;
    public array $errors = [];

    public function collection(Collection $rows)
    {
        // Called manually sheet-wise from import()
    }

    public function import(string $filePath): array
    {
        $sheets = Excel::toArray([], $filePath);

        DB::transaction(function () use ($sheets) {
            $this->importBlueprints($sheets[3] ?? []);
            $this->importBlueprintSections($sheets[4] ?? []);
        });

        return [
            'created' => $this->created,
            'skipped' => $this->skipped,
            'errors' => $this->errors,
        ];
    }

    private function importBlueprints(array $rows): void
    {
        foreach (array_slice($rows, 1) as $index => $row) {
            $rowNumber = $index + 2;

            $blueprintName = trim($row[0] ?? '');
            $gradeName = trim($row[1] ?? '');
            $streamName = trim($row[2] ?? '');
            $subjectName = trim($row[3] ?? '');
            $examName = trim($row[4] ?? '');
            $durationMinutes = (int) ($row[5] ?? 0);
            $totalMarks = (float) ($row[6] ?? 0);

            if (!$blueprintName || !$gradeName || !$subjectName) {
                $this->skipped++;
                $this->errors[] = "Blueprints Row {$rowNumber}: Blueprint, grade or subject missing.";
                continue;
            }

            $grade = Grade::whereRaw('LOWER(name) = ?', [strtolower($gradeName)])
                ->first();

            if (!$grade) {
                $this->skipped++;
                $this->errors[] = "Blueprints Row {$rowNumber}: Grade not found - {$gradeName}.";
                continue;
            }

            $streamId = null;

            if ($streamName) {
                $stream = Stream::whereRaw('LOWER(name) = ?', [strtolower($streamName)])
                    ->first();

                if (!$stream) {
                    $this->skipped++;
                    $this->errors[] = "Blueprints Row {$rowNumber}: Stream not found - {$streamName}.";
                    continue;
                }

                $streamId = $stream->id;
            }

            $subject = $this->findOrCreateSubject(
                $grade->id,
                $streamId,
                $subjectName
            );

            $examNameModel = null;

            if ($examName) {
                $examNameModel = ExamName::firstOrCreate(
                    ['name' => $examName],
                    ['is_active' => true]
                );
            }

            $exists = PaperBlueprint::where('name', $blueprintName)
                ->where('grade_id', $grade->id)
                ->where('stream_id', $streamId)
                ->where('subject_id', $subject->id)
                ->where('exam_name_id', $examNameModel?->id)
                ->exists();

            if ($exists) {
                $this->skipped++;
                continue;
            }

            PaperBlueprint::create([
                'name' => $blueprintName,
                'grade_id' => $grade->id,
                'stream_id' => $streamId,
                'subject_id' => $subject->id,
                'exam_name_id' => $examNameModel?->id,
                'duration_minutes' => $durationMinutes ?: null,
                'total_marks' => $totalMarks,
                'is_active' => true,
            ]);

            $this->created++;
        }
    }

    private function importBlueprintSections(array $rows): void
    {
        foreach (array_slice($rows, 1) as $index => $row) {
            $rowNumber = $index + 2;

            $blueprintName = trim($row[0] ?? '');
            $sectionName = trim($row[1] ?? '');
            $questionTypeSlug = trim($row[2] ?? '');
            $difficulty = trim($row[3] ?? '');
            $questionCount = (int) ($row[4] ?? 0);
            $marksPerQuestion = (float) ($row[5] ?? 0);
            $instructions = trim($row[6] ?? '');
            $sortOrder = (int) ($row[7] ?? 0);

            if (!$blueprintName || !$sectionName || !$questionTypeSlug || !$questionCount || !$marksPerQuestion) {
                $this->skipped++;
                $this->errors[] = "Blueprint Sections Row {$rowNumber}: Required data missing.";
                continue;
            }

            $blueprint = PaperBlueprint::where('name', $blueprintName)->first();

            if (!$blueprint) {
                $this->skipped++;
                $this->errors[] = "Blueprint Sections Row {$rowNumber}: Blueprint not found - {$blueprintName}.";
                continue;
            }

            $questionType = QuestionTypeMaster::where('slug', $questionTypeSlug)->first();

            if (!$questionType) {
                $this->skipped++;
                $this->errors[] = "Blueprint Sections Row {$rowNumber}: Question type not found - {$questionTypeSlug}.";
                continue;
            }

            $exists = $blueprint->sections()
                ->where('section_name', $sectionName)
                ->where('question_type_master_id', $questionType->id)
                ->where('difficulty', $difficulty ?: null)
                ->exists();

            if ($exists) {
                $this->skipped++;
                continue;
            }

            $blueprint->sections()->create([
                'section_name' => $sectionName,
                'question_type_master_id' => $questionType->id,
                'difficulty' => $difficulty ?: null,
                'question_count' => $questionCount,
                'marks_per_question' => $marksPerQuestion,
                'instructions' => $instructions ?: null,
                'sort_order' => $sortOrder,
            ]);

            $this->created++;
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
