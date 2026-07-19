<?php

namespace App\Services\Scheduling\Constraints;

use App\Services\Scheduling\ConstraintInterface;
use App\Services\Scheduling\ScheduleGrid;

class LunchBreakConstraint implements ConstraintInterface
{
    public function passes(
        ScheduleGrid $grid,
        array $candidate,
        int $weekday,
        int $periodNo
    ): bool {
        $lunchPeriods = $candidate['lunch_break_periods'] ?? [];

        if (!is_array($lunchPeriods)) {
            $lunchPeriods = [$lunchPeriods];
        }

        $lunchPeriods = array_map('intval', $lunchPeriods);

        if (in_array($periodNo, $lunchPeriods, true)) {
            return false;
        }

        if (
            !empty($candidate['double_period']) &&
            in_array($periodNo + 1, $lunchPeriods, true)
        ) {
            return false;
        }

        if (
            !empty($candidate['triple_period']) &&
            (
                in_array($periodNo + 1, $lunchPeriods, true) ||
                in_array($periodNo + 2, $lunchPeriods, true)
            )
        ) {
            return false;
        }

        return true;
    }

    public function message(): string
    {
        return 'The selected period overlaps with the lunch break.';
    }

    public function penalty(): int
    {
        return 1000000;
    }
}