<?php

namespace App\Services\Scheduling;

use Illuminate\Support\Collection;

class GeneticScheduler
{
    protected ConstraintManager $constraints;

    protected Collection $candidates;

    protected int $workingDays = 6;

    protected int $periodsPerDay = 8;

    protected int $populationSize = 100;

    protected int $generations = 300;

    protected float $mutationRate = 0.08;

    protected float $crossoverRate = 0.80;

    protected int $eliteCount = 5;

    protected array $population = [];

    protected ?array $bestChromosome = null;

    protected int $bestFitness = PHP_INT_MAX;

    public function __construct(
        ConstraintManager $constraints
    ) {
        $this->constraints = $constraints;
    }

    public function generate(
        Collection $candidates,
        int $workingDays,
        int $periodsPerDay
    ): ScheduleGrid {
        $this->candidates = $candidates
            ->sortByDesc('priority')
            ->values();

        $this->workingDays = $workingDays;
        $this->periodsPerDay = $periodsPerDay;

        $genes = $this->buildGenes();

        if (empty($genes)) {
            return new ScheduleGrid(
                $this->workingDays,
                $this->periodsPerDay
            );
        }

        $this->population = $this->createInitialPopulation($genes);

        for ($generation = 1; $generation <= $this->generations; $generation++) {
            $evaluated = $this->evaluatePopulation();

            $best = $evaluated[0];

            if ($best['fitness'] < $this->bestFitness) {
                $this->bestFitness = $best['fitness'];
                $this->bestChromosome = $best['chromosome'];
            }

            if ($this->bestFitness === 0) {
                break;
            }

            $this->population = $this->createNextGeneration($evaluated);
        }

        return $this->chromosomeToGrid(
            $this->bestChromosome ?? $this->population[0]
        );
    }

    protected function buildGenes(): array
    {
        $genes = [];

        foreach ($this->candidates as $candidateIndex => $candidate) {
            $remainingPeriods = (int) (
                $candidate['remaining_periods']
                ?? $candidate['weekly_periods']
                ?? 0
            );

            $blockSize = $this->blockSize($candidate);

            while ($remainingPeriods > 0) {
                $currentBlockSize = min($blockSize, $remainingPeriods);

                $genes[] = [
                    'candidate_index' => $candidateIndex,
                    'block_size' => $currentBlockSize,
                    'weekday' => null,
                    'period_no' => null,
                ];

                $remainingPeriods -= $currentBlockSize;
            }
        }

        return $genes;
    }

    protected function createInitialPopulation(array $genes): array
    {
        $population = [];

        for ($i = 0; $i < $this->populationSize; $i++) {
            $chromosome = $genes;

            foreach ($chromosome as &$gene) {
                $slot = $this->randomSlot($gene['block_size']);

                $gene['weekday'] = $slot['weekday'];
                $gene['period_no'] = $slot['period_no'];
            }

            unset($gene);

            shuffle($chromosome);

            $population[] = $chromosome;
        }

        return $population;
    }

    protected function evaluatePopulation(): array
    {
        $evaluated = [];

        foreach ($this->population as $chromosome) {
            $evaluated[] = [
                'chromosome' => $chromosome,
                'fitness' => $this->calculateFitness($chromosome),
            ];
        }

        usort(
            $evaluated,
            fn (array $first, array $second): int =>
                $first['fitness'] <=> $second['fitness']
        );

        return $evaluated;
    }

    protected function calculateFitness(array $chromosome): int
    {
        $grid = new ScheduleGrid(
            $this->workingDays,
            $this->periodsPerDay
        );

        $penalty = 0;
        $teacherSlots = [];
        $subjectDailyCounts = [];

        foreach ($chromosome as $gene) {
            $candidate = $this->candidates[$gene['candidate_index']];

            $weekday = (int) $gene['weekday'];
            $periodNo = (int) $gene['period_no'];
            $blockSize = (int) $gene['block_size'];

            if (!$this->isValidBlockPosition(
                $weekday,
                $periodNo,
                $blockSize
            )) {
                $penalty += 1000000;

                continue;
            }

            $candidate['teacher_schedule'] = $teacherSlots;

            if (!$this->constraints->passes(
                $grid,
                $candidate,
                $weekday,
                $periodNo
            )) {
                $penalty += $this->constraints->penalty(
                    $grid,
                    $candidate,
                    $weekday,
                    $periodNo
                );

                continue;
            }

            $blockAvailable = true;

            for ($offset = 0; $offset < $blockSize; $offset++) {
                $currentPeriod = $periodNo + $offset;

                if (!$grid->isEmpty($weekday, $currentPeriod)) {
                    $blockAvailable = false;
                    $penalty += 1000000;

                    break;
                }

                if (
                    isset(
                        $teacherSlots[$candidate['teacher_id']]
                        [$weekday]
                        [$currentPeriod]
                    )
                ) {
                    $blockAvailable = false;
                    $penalty += 1000000;

                    break;
                }
            }

            if (!$blockAvailable) {
                continue;
            }

            $subjectId = (int) $candidate['subject_id'];

            $currentDailyCount = $subjectDailyCounts
                [$subjectId]
                [$weekday]
                ?? 0;

            $maxPeriodsPerDay = (int) (
                $candidate['max_periods_per_day']
                ?? $blockSize
            );

            if (
                $maxPeriodsPerDay > 0 &&
                ($currentDailyCount + $blockSize) > $maxPeriodsPerDay
            ) {
                $penalty += 10000;
            }

            for ($offset = 0; $offset < $blockSize; $offset++) {
                $currentPeriod = $periodNo + $offset;

                $grid->set(
                    $weekday,
                    $currentPeriod,
                    $candidate
                );

                $teacherSlots
                    [$candidate['teacher_id']]
                    [$weekday]
                    [$currentPeriod] = true;
            }

            $subjectDailyCounts
                [$subjectId]
                [$weekday] = $currentDailyCount + $blockSize;
        }

        $penalty += $this->calculateDistributionPenalty(
            $subjectDailyCounts
        );

        $penalty += $grid->emptyCount() * 10;

        return $penalty;
    }

    protected function createNextGeneration(array $evaluated): array
    {
        $nextGeneration = [];

        $eliteCount = min(
            $this->eliteCount,
            count($evaluated)
        );

        for ($i = 0; $i < $eliteCount; $i++) {
            $nextGeneration[] = $evaluated[$i]['chromosome'];
        }

        while (count($nextGeneration) < $this->populationSize) {
            $parentOne = $this->selectParent($evaluated);
            $parentTwo = $this->selectParent($evaluated);

            if ($this->randomFloat() <= $this->crossoverRate) {
                [$childOne, $childTwo] = $this->crossover(
                    $parentOne,
                    $parentTwo
                );
            } else {
                $childOne = $parentOne;
                $childTwo = $parentTwo;
            }

            $childOne = $this->mutate($childOne);
            $childTwo = $this->mutate($childTwo);

            $nextGeneration[] = $childOne;

            if (count($nextGeneration) < $this->populationSize) {
                $nextGeneration[] = $childTwo;
            }
        }

        return $nextGeneration;
    }

    protected function selectParent(array $evaluated): array
    {
        $tournamentSize = min(5, count($evaluated));

        $selected = [];

        for ($i = 0; $i < $tournamentSize; $i++) {
            $selected[] = $evaluated[
                array_rand($evaluated)
            ];
        }

        usort(
            $selected,
            fn (array $first, array $second): int =>
                $first['fitness'] <=> $second['fitness']
        );

        return $selected[0]['chromosome'];
    }

    protected function crossover(
        array $parentOne,
        array $parentTwo
    ): array {
        $geneCount = count($parentOne);

        if ($geneCount < 2) {
            return [$parentOne, $parentTwo];
        }

        $point = random_int(1, $geneCount - 1);

        $childOne = array_merge(
            array_slice($parentOne, 0, $point),
            array_slice($parentTwo, $point)
        );

        $childTwo = array_merge(
            array_slice($parentTwo, 0, $point),
            array_slice($parentOne, $point)
        );

        return [$childOne, $childTwo];
    }

    protected function mutate(array $chromosome): array
    {
        foreach ($chromosome as &$gene) {
            if ($this->randomFloat() > $this->mutationRate) {
                continue;
            }

            $slot = $this->randomSlot(
                (int) $gene['block_size']
            );

            $gene['weekday'] = $slot['weekday'];
            $gene['period_no'] = $slot['period_no'];
        }

        unset($gene);

        if (
            count($chromosome) > 1 &&
            $this->randomFloat() <= $this->mutationRate
        ) {
            $firstIndex = array_rand($chromosome);
            $secondIndex = array_rand($chromosome);

            [
                $chromosome[$firstIndex],
                $chromosome[$secondIndex],
            ] = [
                $chromosome[$secondIndex],
                $chromosome[$firstIndex],
            ];
        }

        return $chromosome;
    }

    protected function chromosomeToGrid(array $chromosome): ScheduleGrid
    {
        $grid = new ScheduleGrid(
            $this->workingDays,
            $this->periodsPerDay
        );

        $teacherSlots = [];

        foreach ($chromosome as $gene) {
            $candidate = $this->candidates[
                $gene['candidate_index']
            ];

            $weekday = (int) $gene['weekday'];
            $periodNo = (int) $gene['period_no'];
            $blockSize = (int) $gene['block_size'];

            if (!$this->isValidBlockPosition(
                $weekday,
                $periodNo,
                $blockSize
            )) {
                continue;
            }

            $candidate['teacher_schedule'] = $teacherSlots;

            if (!$this->constraints->passes(
                $grid,
                $candidate,
                $weekday,
                $periodNo
            )) {
                continue;
            }

            $canAllocate = true;

            for ($offset = 0; $offset < $blockSize; $offset++) {
                $currentPeriod = $periodNo + $offset;

                if (
                    !$grid->isEmpty($weekday, $currentPeriod) ||
                    isset(
                        $teacherSlots[$candidate['teacher_id']]
                        [$weekday]
                        [$currentPeriod]
                    )
                ) {
                    $canAllocate = false;

                    break;
                }
            }

            if (!$canAllocate) {
                continue;
            }

            for ($offset = 0; $offset < $blockSize; $offset++) {
                $currentPeriod = $periodNo + $offset;

                $grid->set(
                    $weekday,
                    $currentPeriod,
                    $candidate
                );

                $teacherSlots
                    [$candidate['teacher_id']]
                    [$weekday]
                    [$currentPeriod] = true;
            }
        }

        return $grid;
    }

    protected function calculateDistributionPenalty(
        array $subjectDailyCounts
    ): int {
        $penalty = 0;

        foreach ($subjectDailyCounts as $dailyCounts) {
            if (empty($dailyCounts)) {
                continue;
            }

            $maximum = max($dailyCounts);
            $minimum = min($dailyCounts);

            $penalty += ($maximum - $minimum) * 100;
        }

        return $penalty;
    }

    protected function randomSlot(int $blockSize): array
    {
        $maximumStartPeriod = max(
            1,
            $this->periodsPerDay - $blockSize + 1
        );

        return [
            'weekday' => random_int(1, $this->workingDays),
            'period_no' => random_int(1, $maximumStartPeriod),
        ];
    }

    protected function isValidBlockPosition(
        int $weekday,
        int $periodNo,
        int $blockSize
    ): bool {
        return $weekday >= 1
            && $weekday <= $this->workingDays
            && $periodNo >= 1
            && ($periodNo + $blockSize - 1) <= $this->periodsPerDay;
    }

    protected function blockSize(array $candidate): int
    {
        if (!empty($candidate['triple_period'])) {
            return 3;
        }

        if (!empty($candidate['double_period'])) {
            return 2;
        }

        return 1;
    }

    protected function randomFloat(): float
    {
        return random_int(0, 1000000) / 1000000;
    }

    public function setPopulationSize(int $populationSize): self
    {
        $this->populationSize = max(10, $populationSize);

        return $this;
    }

    public function setGenerations(int $generations): self
    {
        $this->generations = max(1, $generations);

        return $this;
    }

    public function setMutationRate(float $mutationRate): self
    {
        $this->mutationRate = min(
            1,
            max(0, $mutationRate)
        );

        return $this;
    }

    public function setCrossoverRate(float $crossoverRate): self
    {
        $this->crossoverRate = min(
            1,
            max(0, $crossoverRate)
        );

        return $this;
    }

    public function setEliteCount(int $eliteCount): self
    {
        $this->eliteCount = max(1, $eliteCount);

        return $this;
    }

    public function bestFitness(): int
    {
        return $this->bestFitness;
    }
}