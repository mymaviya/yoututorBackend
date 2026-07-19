<?php

namespace App\Services\Scheduling\Constraints;

use App\Services\Scheduling\ConstraintInterface;
use App\Services\Scheduling\ScheduleGrid;

class ConsecutivePeriodConstraint implements ConstraintInterface
{
    public function passes(
        ScheduleGrid $grid,
        array $candidate,
        int $weekday,
        int $periodNo
    ): bool {

        $periods = $grid->all()[$weekday];

        $left = 0;
        $right = 0;

        /*
        |--------------------------------------------------------------------------
        | Count previous consecutive periods
        |--------------------------------------------------------------------------
        */

        for ($i = $periodNo - 1; $i >= 1; $i--) {

            $slot = $periods[$i];

            if (
                ($slot['teacher_id'] ?? null) == $candidate['teacher_id']
            ) {
                $left++;
            } else {
                break;
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Count next consecutive periods
        |--------------------------------------------------------------------------
        */

        $maxPeriods = count($periods);

        for ($i = $periodNo + 1; $i <= $maxPeriods; $i++) {

            $slot = $periods[$i];

            if (
                ($slot['teacher_id'] ?? null) == $candidate['teacher_id']
            ) {
                $right++;
            } else {
                break;
            }
        }

        $consecutive = $left + $right + 1;

        /*
        |--------------------------------------------------------------------------
        | Teacher consecutive limit
        |--------------------------------------------------------------------------
        */

        if (
            isset($candidate['max_consecutive_periods']) &&
            $candidate['max_consecutive_periods'] &&
            $consecutive > $candidate['max_consecutive_periods']
        ) {
            return false;
        }

        /*
        |--------------------------------------------------------------------------
        | Double Period Required
        |--------------------------------------------------------------------------
        */

        if (!empty($candidate['double_period'])) {

            if ($periodNo >= $maxPeriods) {
                return false;
            }

            if (!$grid->isEmpty($weekday, $periodNo + 1)) {
                return false;
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Triple Period Required
        |--------------------------------------------------------------------------
        */

        if (!empty($candidate['triple_period'])) {

            if ($periodNo >= ($maxPeriods - 1)) {
                return false;
            }

            if (
                !$grid->isEmpty($weekday, $periodNo + 1) ||
                !$grid->isEmpty($weekday, $periodNo + 2)
            ) {
                return false;
            }
        }

        return true;
    }

    public function message(): string
    {
        return 'Consecutive period rule violated.';
    }

    public function penalty(): int
    {
        return 1000000;
    }
}