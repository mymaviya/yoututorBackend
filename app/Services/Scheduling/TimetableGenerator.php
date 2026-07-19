<?php

namespace App\Services\Scheduling;

use Illuminate\Support\Collection;

class TimetableGenerator
{
    protected ConstraintManager $constraints;

    protected ScheduleGrid $grid;

    protected Collection $candidates;

    public function __construct(
        ConstraintManager $constraints
    ) {
        $this->constraints = $constraints;
    }

    /**
     * Generate timetable.
     */
    public function generate(
        ScheduleGrid $grid,
        Collection $candidates
    ): ScheduleGrid {

        $this->grid = $grid;
        $this->candidates = $candidates;

        /*
        |--------------------------------------------------------------------------
        | Highest priority first
        |--------------------------------------------------------------------------
        */

        $this->candidates = $this->candidates
            ->sortByDesc('priority')
            ->values();

        foreach ($this->candidates as $index => &$candidate) {

            while ($candidate['remaining_periods'] > 0) {

                $placed = $this->placeCandidate($candidate);

                if (!$placed) {
                    break;
                }

                $candidate['remaining_periods']--;
                $candidate['allocated']++;
            }
        }

        return $this->grid;
    }

    /**
     * Place one subject occurrence.
     */
    protected function placeCandidate(
        array $candidate
    ): bool {

        $best = null;
        $lowestPenalty = PHP_INT_MAX;

        foreach ($this->grid->emptySlots() as $slot) {

            if (
                !$this->constraints->passes(
                    $this->grid,
                    $candidate,
                    $slot['weekday'],
                    $slot['period_no']
                )
            ) {
                continue;
            }

            $penalty = $this->constraints->penalty(
                $this->grid,
                $candidate,
                $slot['weekday'],
                $slot['period_no']
            );

            if ($penalty < $lowestPenalty) {

                $lowestPenalty = $penalty;

                $best = $slot;
            }
        }

        if (!$best) {
            return false;
        }

        $this->allocate(
            $candidate,
            $best['weekday'],
            $best['period_no']
        );

        return true;
    }

    /**
     * Allocate candidate.
     */
    protected function allocate(
        array $candidate,
        int $weekday,
        int $periodNo
    ): void {

        $this->grid->set(
            $weekday,
            $periodNo,
            $candidate
        );

        /*
        |--------------------------------------------------------------------------
        | Double Period
        |--------------------------------------------------------------------------
        */

        if (!empty($candidate['double_period'])) {

            $this->grid->set(
                $weekday,
                $periodNo + 1,
                $candidate
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Triple Period
        |--------------------------------------------------------------------------
        */

        if (!empty($candidate['triple_period'])) {

            $this->grid->set(
                $weekday,
                $periodNo + 1,
                $candidate
            );

            $this->grid->set(
                $weekday,
                $periodNo + 2,
                $candidate
            );
        }
    }

    /**
     * Scheduler statistics.
     */
    public function statistics(): array
    {
        return [

            'allocated_slots' => $this->grid->allocatedCount(),

            'empty_slots' => $this->grid->emptyCount(),

            'utilization' => round(

                (
                    $this->grid->allocatedCount() /

                    max(
                        1,
                        $this->grid->allocatedCount()
                        + $this->grid->emptyCount()
                    )
                ) * 100,

                2
            ),
        ];
    }
}