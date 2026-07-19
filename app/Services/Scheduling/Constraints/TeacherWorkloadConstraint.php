<?php

namespace App\Services\Scheduling\Constraints;

use App\Services\Scheduling\ConstraintInterface;
use App\Services\Scheduling\ScheduleGrid;

class TeacherWorkloadConstraint implements ConstraintInterface
{
    public function passes(
        ScheduleGrid $grid,
        array $candidate,
        int $weekday,
        int $periodNo
    ): bool {

        $teacherId = $candidate['teacher_id'];

        $weeklyCount = 0;
        $dailyCount = 0;

        foreach ($grid->all() as $day => $periods) {

            foreach ($periods as $slot) {

                if (
                    ($slot['teacher_id'] ?? null) != $teacherId
                ) {
                    continue;
                }

                $weeklyCount++;

                if ($day == $weekday) {
                    $dailyCount++;
                }
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Weekly Limit
        |--------------------------------------------------------------------------
        */

        if (
            isset($candidate['max_teacher_periods']) &&
            $candidate['max_teacher_periods'] !== null &&
            ($weeklyCount + 1) > (int) $candidate['max_teacher_periods']
        ) {
            return false;
        }

        /*
        |--------------------------------------------------------------------------
        | Daily Limit
        |--------------------------------------------------------------------------
        */

        if (
            isset($candidate['max_teacher_periods_per_day']) &&
            $candidate['max_teacher_periods_per_day'] !== null &&
            ($dailyCount + 1) > (int) $candidate['max_teacher_periods_per_day']
        ) {
            return false;
        }

        /*
        |--------------------------------------------------------------------------
        | Minimum Daily Load (Soft Check)
        |--------------------------------------------------------------------------
        */

        if (
            isset($candidate['min_teacher_periods_per_day']) &&
            $candidate['min_teacher_periods_per_day'] > 0 &&
            $dailyCount === 0
        ) {
            // Allow placement. Optimizer may improve later.
        }

        return true;
    }

    public function message(): string
    {
        return 'Teacher workload limit exceeded.';
    }

    public function penalty(): int
    {
        return 1000000;
    }
}