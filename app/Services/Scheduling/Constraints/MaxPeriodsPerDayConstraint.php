<?php

namespace App\Services\Scheduling\Constraints;

use App\Services\Scheduling\ConstraintInterface;
use App\Services\Scheduling\ScheduleGrid;

class MaxPeriodsPerDayConstraint implements ConstraintInterface
{
    public function passes(
        ScheduleGrid $grid,
        array $candidate,
        int $weekday,
        int $periodNo
    ): bool {

        $teacherCount = 0;
        $subjectCount = 0;

        foreach ($grid->all()[$weekday] as $slot) {

            if (
                !empty($slot['teacher_id']) &&
                $slot['teacher_id'] == $candidate['teacher_id']
            ) {
                $teacherCount++;
            }

            if (
                !empty($slot['subject_id']) &&
                $slot['subject_id'] == $candidate['subject_id']
            ) {
                $subjectCount++;
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Teacher Daily Limit
        |--------------------------------------------------------------------------
        */

        if (
            isset($candidate['max_teacher_periods_per_day']) &&
            $candidate['max_teacher_periods_per_day'] !== null &&
            $teacherCount >= $candidate['max_teacher_periods_per_day']
        ) {
            return false;
        }

        /*
        |--------------------------------------------------------------------------
        | Subject Daily Limit
        |--------------------------------------------------------------------------
        */

        if (
            isset($candidate['max_periods_per_day']) &&
            $candidate['max_periods_per_day'] !== null &&
            $subjectCount >= $candidate['max_periods_per_day']
        ) {
            return false;
        }

        return true;
    }

    public function message(): string
    {
        return 'Maximum periods per day exceeded.';
    }

    public function penalty(): int
    {
        // Hard Constraint
        return 1000000;
    }
}