<?php

namespace App\Imports;

use App\Models\Subject;
use App\Models\SubjectPeriodAllocation;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Concerns\ToCollection;

class SubjectPeriodAllocationImport implements ToCollection
{
    private const CATEGORIES = ['major', 'minor', 'language', 'elective', 'lab', 'activity'];

    public function __construct(
        protected int $subscriptionId,
        protected int $gradeId,
        protected ?int $academicYearId = null,
        protected ?int $sectionId = null,
        protected ?int $streamId = null
    ) {}

    public function collection(Collection $rows): void
    {
        $rows = $rows->slice(1)
            ->filter(fn ($row) => collect($row)->filter(fn ($value) => filled($value))->isNotEmpty())
            ->values();

        if ($rows->isEmpty()) {
            throw ValidationException::withMessages([
                'file' => 'The import file does not contain any allocation rows.',
            ]);
        }

        $subjects = Subject::query()
            ->where('subscription_id', $this->subscriptionId)
            ->where('grade_id', $this->gradeId)
            ->when(
                $this->streamId,
                fn ($query, $streamId) => $query->where(function ($query) use ($streamId) {
                    $query->whereNull('stream_id')->orWhere('stream_id', $streamId);
                })
            )
            ->where('is_active', true)
            ->get()
            ->keyBy(fn (Subject $subject) => $this->normaliseKey($subject->name));

        $teacherNames = $rows
            ->pluck(4)
            ->map(fn ($name) => trim((string) $name))
            ->filter()
            ->unique()
            ->values();

        $teachers = User::query()
            ->where('subscription_id', $this->subscriptionId)
            ->whereIn('name', $teacherNames)
            ->get()
            ->keyBy(fn (User $teacher) => $this->normaliseKey($teacher->name));

        $prepared = [];
        $errors = [];
        $seenSubjects = [];

        foreach ($rows as $index => $row) {
            $excelRow = $index + 2;
            $subjectName = trim((string) ($row[0] ?? ''));
            $subjectKey = $this->normaliseKey($subjectName);
            $subject = $subjects->get($subjectKey);

            if ($subjectName === '') {
                $errors["row_$excelRow"][] = 'Subject is required.';
                continue;
            }

            if (! $subject) {
                $errors["row_$excelRow"][] = "Subject '$subjectName' is not an active subject for the selected grade and stream.";
                continue;
            }

            if (isset($seenSubjects[$subject->id])) {
                $errors["row_$excelRow"][] = "Subject '$subjectName' appears more than once in the import file.";
                continue;
            }

            $seenSubjects[$subject->id] = true;

            $category = strtolower(trim((string) ($row[1] ?? 'major')));
            $weeklyPeriods = $this->integerValue($row[2] ?? null, 6);
            $maxPerDay = $this->integerValue($row[3] ?? null, 2);
            $teacherName = trim((string) ($row[4] ?? ''));
            $teacher = $teacherName !== ''
                ? $teachers->get($this->normaliseKey($teacherName))
                : null;
            $priority = $this->integerValue($row[10] ?? null, 5);

            if (! in_array($category, self::CATEGORIES, true)) {
                $errors["row_$excelRow"][] = 'Category must be one of: ' . implode(', ', self::CATEGORIES) . '.';
            }

            if ($weeklyPeriods < 0 || $weeklyPeriods > 60) {
                $errors["row_$excelRow"][] = 'Weekly Periods must be between 0 and 60.';
            }

            if ($maxPerDay < 1 || $maxPerDay > 10) {
                $errors["row_$excelRow"][] = 'Max Periods Per Day must be between 1 and 10.';
            }

            if ($weeklyPeriods > ($maxPerDay * 6)) {
                $errors["row_$excelRow"][] = 'Weekly Periods exceeds the six-day capacity allowed by Max Periods Per Day.';
            }

            if ($priority < 1 || $priority > 10) {
                $errors["row_$excelRow"][] = 'Priority must be between 1 and 10.';
            }

            if ($teacherName !== '' && ! $teacher) {
                $errors["row_$excelRow"][] = "Preferred Teacher '$teacherName' was not found in this subscription.";
            }

            $preferDouble = $this->booleanValue($row[5] ?? null);
            $isParallel = $this->booleanValue($row[8] ?? null);
            $parallelCode = $isParallel ? trim((string) ($row[9] ?? '')) : null;

            if ($preferDouble && $weeklyPeriods < 2) {
                $errors["row_$excelRow"][] = 'Double Period requires at least two weekly periods.';
            }

            if ($isParallel && $parallelCode === '') {
                $errors["row_$excelRow"][] = 'Parallel Group Code is required when Parallel is enabled.';
            }

            $prepared[] = [
                'subject_id' => $subject->id,
                'preferred_teacher_id' => $teacher?->id,
                'subject_category' => $category,
                'weekly_periods' => $weeklyPeriods,
                'max_periods_per_day' => $maxPerDay,
                'prefer_double_period' => $preferDouble,
                'prefer_morning' => $this->booleanValue($row[6] ?? null),
                'prefer_saturday' => $this->booleanValue($row[7] ?? null),
                'is_parallel_subject' => $isParallel,
                'parallel_group_code' => $parallelCode ?: null,
                'priority' => $priority,
                'is_active' => true,
            ];
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        DB::transaction(function () use ($prepared) {
            foreach ($prepared as $item) {
                SubjectPeriodAllocation::query()->updateOrCreate(
                    [
                        'subscription_id' => $this->subscriptionId,
                        'academic_year_id' => $this->academicYearId,
                        'grade_id' => $this->gradeId,
                        'section_id' => $this->sectionId,
                        'stream_id' => $this->streamId,
                        'subject_id' => $item['subject_id'],
                    ],
                    array_merge($item, [
                        'subscription_id' => $this->subscriptionId,
                        'academic_year_id' => $this->academicYearId,
                        'grade_id' => $this->gradeId,
                        'section_id' => $this->sectionId,
                        'stream_id' => $this->streamId,
                    ])
                );
            }
        });
    }

    private function booleanValue(mixed $value): bool
    {
        return in_array(
            strtolower(trim((string) $value)),
            ['1', 'yes', 'y', 'true', 'on'],
            true
        );
    }

    private function integerValue(mixed $value, int $default): int
    {
        return is_numeric($value) ? (int) $value : $default;
    }

    private function normaliseKey(string $value): string
    {
        return Str::lower(trim($value));
    }
}
