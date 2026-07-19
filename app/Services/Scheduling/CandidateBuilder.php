<?php

namespace App\Services\Scheduling;

use App\Models\SubjectPeriodAllocation;
use App\Models\TeacherAssignment;
use Illuminate\Support\Collection;

class CandidateBuilder
{
    /**
     * Build timetable candidates for one class.
     */
    public function build(
        int $subscriptionId,
        int $academicYearId,
        int $gradeId,
        int $sectionId,
        ?int $streamId = null
    ): Collection {

        $assignments = TeacherAssignment::query()
            ->where('subscription_id', $subscriptionId)
            ->where('grade_id', $gradeId)
            ->where('section_id', $sectionId)
            ->when(
                $streamId,
                fn ($q) => $q->where('stream_id', $streamId)
            )
            ->where('is_active', true)
            ->with([
                'teacher',
                'subject',
            ])
            ->get();

        $periodAllocations = SubjectPeriodAllocation::query()
            ->where('subscription_id', $subscriptionId)
            ->where('academic_year_id', $academicYearId)
            ->where('grade_id', $gradeId)
            ->where('section_id', $sectionId)
            ->when(
                $streamId,
                fn ($q) => $q->where('stream_id', $streamId)
            )
            ->get()
            ->keyBy('subject_id');

        $candidates = collect();

        foreach ($assignments as $assignment) {

            $allocation = $periodAllocations->get(
                $assignment->subject_id
            );

            if (!$allocation) {
                continue;
            }

            $weeklyPeriods = (int) (
                $assignment->weekly_required_periods
                ?? $allocation->weekly_periods
            );

            $candidates->push([

                'teacher_id' => $assignment->teacher_id,
                'teacher_name' => optional($assignment->teacher)->name,

                'subject_id' => $assignment->subject_id,
                'subject_name' => optional($assignment->subject)->name,

                'grade_id' => $assignment->grade_id,
                'section_id' => $assignment->section_id,
                'stream_id' => $assignment->stream_id,

                'weekly_periods' => $weeklyPeriods,
                'remaining_periods' => $weeklyPeriods,

                'max_periods_per_day' => $allocation->max_periods_per_day,

                'double_period' => (bool) $allocation->double_period,

                'priority' => $assignment->priority ?? 1,

                'max_teacher_periods' => $assignment->max_periods_per_week,

                'allocated' => 0,
            ]);
        }

        return $candidates
            ->sortByDesc('priority')
            ->values();
    }
}