<?php

namespace App\Services\Scheduling\Constraints;

use App\Services\Scheduling\ConstraintInterface;
use App\Services\Scheduling\ScheduleGrid;

class TeacherConflictConstraint implements ConstraintInterface
{
    /**
     * Checks whether the teacher is already teaching
     * another class in the same period.
     *
     * The Scheduler should pass the current global timetable
     * in $candidate['teacher_schedule'].
     */
    public function passes(
        ScheduleGrid $grid,
        array $candidate,
        int $weekday,
        int $periodNo
    ): bool {

        if (!isset($candidate['teacher_schedule'])) {
            return true;
        }

        $teacherSchedule = $candidate['teacher_schedule'];

        if (
            isset($teacherSchedule[$weekday][$periodNo]) &&
            !empty($teacherSchedule[$weekday][$periodNo])
        ) {
            return false;
        }

        return true;
    }

    public function message(): string
    {
        return 'Teacher is already assigned to another class during this period.';
    }

    public function penalty(): int
    {
        // Hard constraint
        return 1000000;
    }
}