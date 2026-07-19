<?php

namespace App\Services\Scheduling;

interface ConstraintInterface
{
    /**
     * Validate whether a candidate can be placed in the given slot.
     */
    public function passes(
        ScheduleGrid $grid,
        array $candidate,
        int $weekday,
        int $periodNo
    ): bool;

    /**
     * Message returned when validation fails.
     */
    public function message(): string;

    /**
     * Penalty score.
     *
     * Hard constraints should return a very high penalty
     * while soft constraints can return smaller values.
     */
    public function penalty(): int;
}