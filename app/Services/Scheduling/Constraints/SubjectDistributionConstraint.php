<?php

namespace App\Services\Scheduling\Constraints;

use App\Services\Scheduling\ConstraintInterface;
use App\Services\Scheduling\ScheduleGrid;

class SubjectDistributionConstraint implements ConstraintInterface
{
    public function passes(
        ScheduleGrid $grid,
        array $candidate,
        int $weekday,
        int $periodNo
    ): bool {
        $dailySubjectCount = $this->countSubjectPeriodsForDay(
            $grid,
            $candidate['subject_id'],
            $weekday
        );

        $maxPeriodsPerDay = isset($candidate['max_periods_per_day'])
            ? (int) $candidate['max_periods_per_day']
            : 1;

        if ($maxPeriodsPerDay < 1) {
            $maxPeriodsPerDay = 1;
        }

        $requiredBlockSize = $this->requiredBlockSize($candidate);

        if (($dailySubjectCount + $requiredBlockSize) > $maxPeriodsPerDay) {
            return false;
        }

        if (
            !empty($candidate['avoid_consecutive_days']) &&
            $this->hasSubjectOnAdjacentDay(
                $grid,
                $candidate['subject_id'],
                $weekday
            )
        ) {
            return false;
        }

        if (
            !empty($candidate['one_period_per_day']) &&
            $dailySubjectCount > 0
        ) {
            return false;
        }

        return true;
    }

    public function message(): string
    {
        return 'Subject distribution rule violated.';
    }

    public function penalty(): int
    {
        return 1000;
    }

    private function countSubjectPeriodsForDay(
        ScheduleGrid $grid,
        int $subjectId,
        int $weekday
    ): int {
        $days = $grid->all();

        if (!isset($days[$weekday])) {
            return 0;
        }

        $count = 0;

        foreach ($days[$weekday] as $slot) {
            if (($slot['subject_id'] ?? null) === $subjectId) {
                $count++;
            }
        }

        return $count;
    }

    private function hasSubjectOnAdjacentDay(
        ScheduleGrid $grid,
        int $subjectId,
        int $weekday
    ): bool {
        $days = $grid->all();

        foreach ([$weekday - 1, $weekday + 1] as $adjacentDay) {
            if (!isset($days[$adjacentDay])) {
                continue;
            }

            foreach ($days[$adjacentDay] as $slot) {
                if (($slot['subject_id'] ?? null) === $subjectId) {
                    return true;
                }
            }
        }

        return false;
    }

    private function requiredBlockSize(array $candidate): int
    {
        if (!empty($candidate['triple_period'])) {
            return 3;
        }

        if (!empty($candidate['double_period'])) {
            return 2;
        }

        return 1;
    }
}