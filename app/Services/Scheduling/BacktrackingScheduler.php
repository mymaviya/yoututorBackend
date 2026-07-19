<?php

namespace App\Services\Scheduling;

use Illuminate\Support\Collection;

class BacktrackingScheduler
{
    protected ConstraintManager $constraints;

    protected ScheduleGrid $grid;

    protected Collection $candidates;

    protected int $maxIterations = 500000;

    protected int $iterations = 0;

    public function __construct(
        ConstraintManager $constraints
    ) {
        $this->constraints = $constraints;
    }

    /**
     * Generate timetable using recursive backtracking.
     */
    public function generate(
        ScheduleGrid $grid,
        Collection $candidates
    ): ScheduleGrid {

        $this->grid = $grid;

        $this->candidates = $candidates
            ->sortByDesc('priority')
            ->values();

        $this->solve(0);

        return $this->grid;
    }

    /**
     * Recursive solver.
     */
    protected function solve(
        int $candidateIndex
    ): bool {

        if ($this->iterations++ > $this->maxIterations) {
            return false;
        }

        if ($candidateIndex >= $this->candidates->count()) {
            return true;
        }

        $candidate = $this->candidates[$candidateIndex];

        if ($candidate['remaining_periods'] <= 0) {
            return $this->solve($candidateIndex + 1);
        }

        foreach ($this->grid->emptySlots() as $slot) {

            $day = $slot['weekday'];
            $period = $slot['period_no'];

            if (
                !$this->constraints->passes(
                    $this->grid,
                    $candidate,
                    $day,
                    $period
                )
            ) {
                continue;
            }

            $blockSize = $this->blockSize($candidate);

            if (!$this->canAllocateBlock(
                $day,
                $period,
                $blockSize
            )) {
                continue;
            }

            $this->allocate(
                $candidate,
                $day,
                $period,
                $blockSize
            );

            $candidate['remaining_periods'] -= $blockSize;
            $candidate['allocated'] += $blockSize;

            $this->candidates[$candidateIndex] = $candidate;

            if ($candidate['remaining_periods'] <= 0) {

                if ($this->solve($candidateIndex + 1)) {
                    return true;
                }

            } else {

                if ($this->solve($candidateIndex)) {
                    return true;
                }
            }

            $candidate['remaining_periods'] += $blockSize;
            $candidate['allocated'] -= $blockSize;

            $this->candidates[$candidateIndex] = $candidate;

            $this->deallocate(
                $day,
                $period,
                $blockSize
            );
        }

        return false;
    }

    protected function blockSize(
        array $candidate
    ): int {

        if (!empty($candidate['triple_period'])) {
            return 3;
        }

        if (!empty($candidate['double_period'])) {
            return 2;
        }

        return 1;
    }

    protected function canAllocateBlock(
        int $day,
        int $period,
        int $size
    ): bool {

        for ($i = 0; $i < $size; $i++) {

            if (!$this->grid->isEmpty($day, $period + $i)) {
                return false;
            }
        }

        return true;
    }

    protected function allocate(
        array $candidate,
        int $day,
        int $period,
        int $size
    ): void {

        for ($i = 0; $i < $size; $i++) {

            $this->grid->set(
                $day,
                $period + $i,
                $candidate
            );
        }
    }

    protected function deallocate(
        int $day,
        int $period,
        int $size
    ): void {

        for ($i = 0; $i < $size; $i++) {

            $this->grid->clear(
                $day,
                $period + $i
            );
        }
    }

    public function iterations(): int
    {
        return $this->iterations;
    }

    public function setMaxIterations(
        int $iterations
    ): self {

        $this->maxIterations = $iterations;

        return $this;
    }
}