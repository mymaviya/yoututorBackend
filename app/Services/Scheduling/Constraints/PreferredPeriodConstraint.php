<?php

namespace App\Services\Scheduling\Constraints;

use App\Services\Scheduling\ConstraintInterface;
use App\Services\Scheduling\ScheduleGrid;

class PreferredPeriodConstraint implements ConstraintInterface
{
    public function passes(
        ScheduleGrid $grid,
        array $candidate,
        int $weekday,
        int $periodNo
    ): bool {

        /*
        |--------------------------------------------------------------------------
        | Preferred Weekdays
        |--------------------------------------------------------------------------
        */

        if (
            !empty($candidate['preferred_weekdays']) &&
            is_array($candidate['preferred_weekdays'])
        ) {

            if (!in_array($weekday, $candidate['preferred_weekdays'])) {
                return false;
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Preferred Periods
        |--------------------------------------------------------------------------
        */

        if (
            !empty($candidate['preferred_periods']) &&
            is_array($candidate['preferred_periods'])
        ) {

            if (!in_array($periodNo, $candidate['preferred_periods'])) {
                return false;
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Avoid First Period
        |--------------------------------------------------------------------------
        */

        if (
            !empty($candidate['avoid_first_period']) &&
            $periodNo == 1
        ) {
            return false;
        }

        /*
        |--------------------------------------------------------------------------
        | Avoid Last Period
        |--------------------------------------------------------------------------
        */

        if (
            !empty($candidate['avoid_last_period']) &&
            $periodNo == count($grid->all()[$weekday])
        ) {
            return false;
        }

        /*
        |--------------------------------------------------------------------------
        | Preferred Time Window
        |--------------------------------------------------------------------------
        */

        if (
            isset($candidate['preferred_start_period']) &&
            isset($candidate['preferred_end_period'])
        ) {

            if (
                $periodNo < $candidate['preferred_start_period'] ||
                $periodNo > $candidate['preferred_end_period']
            ) {
                return false;
            }
        }

        return true;
    }

    public function message(): string
    {
        return 'Preferred period rule violated.';
    }

    /**
     * Soft constraint.
     */
    public function penalty(): int
    {
        return 100;
    }
}