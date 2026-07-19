<?php

namespace App\Services\Scheduling\Constraints;

use App\Services\Scheduling\ConstraintInterface;
use App\Services\Scheduling\ScheduleGrid;

class ClassConflictConstraint implements ConstraintInterface
{
    /**
     * Ensures that only one subject is allocated
     * to a class in a particular period.
     */
    public function passes(
        ScheduleGrid $grid,
        array $candidate,
        int $weekday,
        int $periodNo
    ): bool {

        return $grid->isEmpty($weekday, $periodNo);
    }

    public function message(): string
    {
        return 'Class already has a subject assigned for this period.';
    }

    public function penalty(): int
    {
        // Hard Constraint
        return 1000000;
    }
}