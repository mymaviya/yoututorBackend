<?php

namespace App\Imports;

use App\Models\Subject;
use App\Models\SubjectPeriodAllocation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;

class SubjectPeriodAllocationImport implements ToCollection
{
    public function __construct(
        protected int $subscriptionId,
        protected int $gradeId,
        protected ?int $academicYearId = null,
        protected ?int $sectionId = null,
        protected ?int $streamId = null
    ) {}

    public function collection(Collection $rows): void
    {
        $rows->shift();

        DB::transaction(function () use ($rows) {
            foreach ($rows as $row) {
                $subjectName = trim((string) ($row[0] ?? ''));

                if (! $subjectName) {
                    continue;
                }

                $subject = Subject::where('subscription_id', $this->subscriptionId)
                    ->where('grade_id', $this->gradeId)
                    ->where('name', $subjectName)
                    ->first();

                if (! $subject) {
                    continue;
                }

                SubjectPeriodAllocation::updateOrCreate(
                    [
                        'subscription_id' => $this->subscriptionId,
                        'academic_year_id' => $this->academicYearId,
                        'grade_id' => $this->gradeId,
                        'section_id' => $this->sectionId,
                        'stream_id' => $this->streamId,
                        'subject_id' => $subject->id,
                    ],
                    [
                        'subject_category' => strtolower($row[1] ?? 'major'),
                        'weekly_periods' => (int) ($row[2] ?? 6),
                        'max_periods_per_day' => (int) ($row[3] ?? 2),
                        'prefer_double_period' => strtolower($row[5] ?? '') === 'yes',
                        'prefer_morning' => strtolower($row[6] ?? '') === 'yes',
                        'prefer_saturday' => strtolower($row[7] ?? '') === 'yes',
                        'is_parallel_subject' => strtolower($row[8] ?? '') === 'yes',
                        'parallel_group_code' => $row[9] ?? null,
                        'priority' => (int) ($row[10] ?? 5),
                        'is_active' => true,
                    ]
                );
            }
        });
    }
}