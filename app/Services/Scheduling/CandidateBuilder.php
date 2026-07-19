<?php

namespace App\Services\Scheduling;

use App\Models\SubjectPeriodAllocation;
use App\Models\TeacherAssignment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class CandidateBuilder
{
    /**
     * Build timetable candidates for one class and section.
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
            ->where('is_active', true)
            ->when(
                $streamId !== null,
                fn (Builder $query) => $query->where('stream_id', $streamId),
                fn (Builder $query) => $query->whereNull('stream_id')
            )
            ->with(['teacher:id,name', 'subject:id,name'])
            ->get();

        $periodAllocations = SubjectPeriodAllocation::query()
            ->where('subscription_id', $subscriptionId)
            ->where('academic_year_id', $academicYearId)
            ->where('grade_id', $gradeId)
            ->where('section_id', $sectionId)
            ->where('is_active', true)
            ->when(
                $streamId !== null,
                fn (Builder $query) => $query->where('stream_id', $streamId),
                fn (Builder $query) => $query->whereNull('stream_id')
            )
            ->get()
            ->keyBy('subject_id');

        return $assignments
            ->map(function (TeacherAssignment $assignment) use ($periodAllocations): ?array {
                $allocation = $periodAllocations->get($assignment->subject_id);

                if (!$allocation || !$assignment->teacher || !$assignment->subject) {
                    return null;
                }

                $weeklyPeriods = (int) $allocation->weekly_periods;

                if ($weeklyPeriods < 1) {
                    return null;
                }

                return [
                    'teacher_assignment_id' => (int) $assignment->id,
                    'subject_period_allocation_id' => (int) $allocation->id,

                    'subscription_id' => (int) $assignment->subscription_id,
                    'academic_year_id' => (int) $allocation->academic_year_id,

                    'teacher_id' => (int) $assignment->teacher_id,
                    'teacher_name' => $assignment->teacher->name,

                    'subject_id' => (int) $assignment->subject_id,
                    'subject_name' => $assignment->subject->name,

                    'grade_id' => (int) $assignment->grade_id,
                    'section_id' => (int) $assignment->section_id,
                    'stream_id' => $assignment->stream_id !== null
                        ? (int) $assignment->stream_id
                        : null,

                    'weekly_periods' => $weeklyPeriods,
                    'remaining_periods' => $weeklyPeriods,
                    'allocated' => 0,

                    'max_periods_per_day' => max(
                        1,
                        (int) ($allocation->max_periods_per_day ?? 1)
                    ),
                    'max_teacher_periods' => $assignment->max_periods_per_week !== null
                        ? (int) $assignment->max_periods_per_week
                        : null,

                    'double_period' => (bool) $allocation->prefer_double_period,
                    'prefer_double_period' => (bool) $allocation->prefer_double_period,
                    'prefer_morning' => (bool) $allocation->prefer_morning,
                    'prefer_last_period' => (bool) $allocation->prefer_last_period,
                    'prefer_saturday' => (bool) $allocation->prefer_saturday,
                    'is_optional' => (bool) $allocation->is_optional,
                    'is_parallel_subject' => (bool) $allocation->is_parallel_subject,
                    'parallel_group_code' => $allocation->parallel_group_code,
                    'subject_category' => $allocation->subject_category,

                    'priority' => max(
                        1,
                        (int) ($assignment->priority ?? $allocation->priority ?? 1)
                    ),
                ];
            })
            ->filter()
            ->sortByDesc('priority')
            ->values();
    }
}
