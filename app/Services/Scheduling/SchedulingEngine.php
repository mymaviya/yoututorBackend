<?php

namespace App\Services\Scheduling;

use App\Services\Scheduling\Constraints\ClassConflictConstraint;
use App\Services\Scheduling\Constraints\ConsecutivePeriodConstraint;
use App\Services\Scheduling\Constraints\LunchBreakConstraint;
use App\Services\Scheduling\Constraints\MaxPeriodsPerDayConstraint;
use App\Services\Scheduling\Constraints\PreferredPeriodConstraint;
use App\Services\Scheduling\Constraints\SubjectDistributionConstraint;
use App\Services\Scheduling\Constraints\TeacherAvailabilityConstraint;
use App\Services\Scheduling\Constraints\TeacherConflictConstraint;
use App\Services\Scheduling\Constraints\TeacherWorkloadConstraint;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use Throwable;

class SchedulingEngine
{
    protected CandidateBuilder $candidateBuilder;

    protected ConstraintManager $constraintManager;

    protected ?ScheduleGrid $generatedGrid = null;

    protected array $statistics = [];

    protected string $algorithm = 'backtracking';

    public function __construct(
        CandidateBuilder $candidateBuilder,
        ConstraintManager $constraintManager
    ) {
        $this->candidateBuilder = $candidateBuilder;
        $this->constraintManager = $constraintManager;
    }

    /**
     * Generate a timetable for one class and section.
     */
    public function generate(array $parameters): array
    {
        $this->validateParameters($parameters);

        $subscriptionId = (int) $parameters['subscription_id'];
        $academicYearId = (int) $parameters['academic_year_id'];
        $gradeId = (int) $parameters['grade_id'];
        $sectionId = (int) $parameters['section_id'];

        $streamId = isset($parameters['stream_id'])
            ? (int) $parameters['stream_id']
            : null;

        $workingDays = (int) ($parameters['working_days'] ?? 6);
        $periodsPerDay = (int) ($parameters['periods_per_day'] ?? 8);

        $this->algorithm = strtolower(
            (string) ($parameters['algorithm'] ?? 'backtracking')
        );

        $candidates = $this->candidateBuilder->build(
            subscriptionId: $subscriptionId,
            academicYearId: $academicYearId,
            gradeId: $gradeId,
            sectionId: $sectionId,
            streamId: $streamId
        );

        $candidates = $this->prepareCandidates(
            $candidates,
            $parameters
        );

        $this->registerConstraints();

        $startedAt = microtime(true);

        try {
            $grid = match ($this->algorithm) {
                'greedy' => $this->generateUsingGreedy(
                    $candidates,
                    $workingDays,
                    $periodsPerDay
                ),

                'genetic' => $this->generateUsingGenetic(
                    $candidates,
                    $workingDays,
                    $periodsPerDay,
                    $parameters
                ),

                'backtracking' => $this->generateUsingBacktracking(
                    $candidates,
                    $workingDays,
                    $periodsPerDay,
                    $parameters
                ),

                default => throw new InvalidArgumentException(
                    "Unsupported scheduling algorithm: {$this->algorithm}"
                ),
            };
        } catch (Throwable $exception) {
            return [
                'success' => false,
                'message' => 'Timetable generation failed.',
                'error' => $exception->getMessage(),
                'algorithm' => $this->algorithm,
                'grid' => [],
                'statistics' => [],
            ];
        }

        $this->generatedGrid = $grid;

        $this->statistics = $this->buildStatistics(
            grid: $grid,
            candidates: $candidates,
            startedAt: $startedAt
        );

        return [
            'success' => true,
            'message' => 'Timetable generated successfully.',
            'algorithm' => $this->algorithm,
            'grid' => $grid->all(),
            'statistics' => $this->statistics,
            'unallocated' => $this->unallocatedCandidates(
                $grid,
                $candidates
            ),
        ];
    }

    /**
     * Register timetable constraints.
     */
    protected function registerConstraints(): void
    {
        $this->constraintManager
            ->clear()
            ->addMany([
                new ClassConflictConstraint(),
                new TeacherConflictConstraint(),
                new TeacherAvailabilityConstraint(),
                new MaxPeriodsPerDayConstraint(),
                new ConsecutivePeriodConstraint(),
                new SubjectDistributionConstraint(),
                new PreferredPeriodConstraint(),
                new LunchBreakConstraint(),
                new TeacherWorkloadConstraint(),
            ]);
    }

    /**
     * Add common scheduling data to every candidate.
     */
    protected function prepareCandidates(
        Collection $candidates,
        array $parameters
    ): Collection {
        $teacherSchedule = $parameters['teacher_schedule'] ?? [];

        $lunchBreakPeriods = $parameters['lunch_break_periods'] ?? [];

        return $candidates
            ->map(function (array $candidate) use (
                $parameters,
                $teacherSchedule,
                $lunchBreakPeriods
            ): array {
                $teacherId = (int) $candidate['teacher_id'];

                $candidate['teacher_schedule'] =
                    $teacherSchedule[$teacherId] ?? [];

                $candidate['lunch_break_periods'] =
                    $lunchBreakPeriods;

                $candidate['max_teacher_periods_per_day'] =
                    $parameters['max_teacher_periods_per_day']
                    ?? $candidate['max_teacher_periods_per_day']
                    ?? null;

                $candidate['max_consecutive_periods'] =
                    $parameters['max_consecutive_periods']
                    ?? $candidate['max_consecutive_periods']
                    ?? 3;

                $candidate['preferred_weekdays'] =
                    $candidate['preferred_weekdays']
                    ?? [];

                $candidate['preferred_periods'] =
                    $candidate['preferred_periods']
                    ?? [];

                $candidate['avoid_first_period'] =
                    (bool) (
                        $candidate['avoid_first_period']
                        ?? false
                    );

                $candidate['avoid_last_period'] =
                    (bool) (
                        $candidate['avoid_last_period']
                        ?? false
                    );

                $candidate['one_period_per_day'] =
                    (bool) (
                        $candidate['one_period_per_day']
                        ?? false
                    );

                $candidate['avoid_consecutive_days'] =
                    (bool) (
                        $candidate['avoid_consecutive_days']
                        ?? false
                    );

                return $candidate;
            })
            ->sortBy([
                fn (array $first, array $second): int =>
                    $this->candidateDifficulty($second)
                    <=> $this->candidateDifficulty($first),

                fn (array $first, array $second): int =>
                    ($second['priority'] ?? 0)
                    <=> ($first['priority'] ?? 0),
            ])
            ->values();
    }

    /**
     * Generate using the greedy scheduler.
     */
    protected function generateUsingGreedy(
        Collection $candidates,
        int $workingDays,
        int $periodsPerDay
    ): ScheduleGrid {
        $grid = new ScheduleGrid(
            $workingDays,
            $periodsPerDay
        );

        $generator = new TimetableGenerator(
            $this->constraintManager
        );

        return $generator->generate(
            $grid,
            $candidates
        );
    }

    /**
     * Generate using recursive backtracking.
     */
    protected function generateUsingBacktracking(
        Collection $candidates,
        int $workingDays,
        int $periodsPerDay,
        array $parameters
    ): ScheduleGrid {
        $grid = new ScheduleGrid(
            $workingDays,
            $periodsPerDay
        );

        $scheduler = new BacktrackingScheduler(
            $this->constraintManager
        );

        $scheduler->setMaxIterations(
            (int) ($parameters['max_iterations'] ?? 500000)
        );

        return $scheduler->generate(
            $grid,
            $candidates
        );
    }

    /**
     * Generate using the genetic scheduler.
     */
    protected function generateUsingGenetic(
        Collection $candidates,
        int $workingDays,
        int $periodsPerDay,
        array $parameters
    ): ScheduleGrid {
        $scheduler = new GeneticScheduler(
            $this->constraintManager
        );

        $scheduler
            ->setPopulationSize(
                (int) ($parameters['population_size'] ?? 100)
            )
            ->setGenerations(
                (int) ($parameters['generations'] ?? 300)
            )
            ->setMutationRate(
                (float) ($parameters['mutation_rate'] ?? 0.08)
            )
            ->setCrossoverRate(
                (float) ($parameters['crossover_rate'] ?? 0.80)
            )
            ->setEliteCount(
                (int) ($parameters['elite_count'] ?? 5)
            );

        return $scheduler->generate(
            $candidates,
            $workingDays,
            $periodsPerDay
        );
    }

    /**
     * Build generation statistics.
     */
    protected function buildStatistics(
        ScheduleGrid $grid,
        Collection $candidates,
        float $startedAt
    ): array {
        $totalSlots = $grid->allocatedCount()
            + $grid->emptyCount();

        $requiredPeriods = $candidates->sum(
            fn (array $candidate): int =>
                (int) ($candidate['weekly_periods'] ?? 0)
        );

        $allocatedPeriods = $grid->allocatedCount();

        return [
            'algorithm' => $this->algorithm,
            'total_slots' => $totalSlots,
            'allocated_slots' => $allocatedPeriods,
            'empty_slots' => $grid->emptyCount(),
            'required_periods' => $requiredPeriods,
            'unallocated_periods' => max(
                0,
                $requiredPeriods - $allocatedPeriods
            ),
            'utilization_percentage' => $totalSlots > 0
                ? round(
                    ($allocatedPeriods / $totalSlots) * 100,
                    2
                )
                : 0,
            'completion_percentage' => $requiredPeriods > 0
                ? round(
                    min(
                        100,
                        ($allocatedPeriods / $requiredPeriods) * 100
                    ),
                    2
                )
                : 100,
            'candidate_count' => $candidates->count(),
            'constraint_count' => $this->constraintManager->count(),
            'execution_time_ms' => round(
                (microtime(true) - $startedAt) * 1000,
                2
            ),
        ];
    }

    /**
     * Return subjects that could not be fully allocated.
     */
    protected function unallocatedCandidates(
        ScheduleGrid $grid,
        Collection $candidates
    ): array {
        $allocated = [];

        foreach ($grid->all() as $periods) {
            foreach ($periods as $slot) {
                $subjectId = $slot['subject_id'] ?? null;
                $teacherId = $slot['teacher_id'] ?? null;

                if (!$subjectId || !$teacherId) {
                    continue;
                }

                $key = "{$subjectId}:{$teacherId}";

                $allocated[$key] = ($allocated[$key] ?? 0) + 1;
            }
        }

        return $candidates
            ->map(function (array $candidate) use ($allocated): array {
                $key = "{$candidate['subject_id']}:{$candidate['teacher_id']}";

                $required = (int) (
                    $candidate['weekly_periods'] ?? 0
                );

                $allocatedPeriods = (int) (
                    $allocated[$key] ?? 0
                );

                return [
                    'teacher_id' => $candidate['teacher_id'],
                    'teacher_name' => $candidate['teacher_name'],
                    'subject_id' => $candidate['subject_id'],
                    'subject_name' => $candidate['subject_name'],
                    'required_periods' => $required,
                    'allocated_periods' => $allocatedPeriods,
                    'remaining_periods' => max(
                        0,
                        $required - $allocatedPeriods
                    ),
                ];
            })
            ->filter(
                fn (array $candidate): bool =>
                    $candidate['remaining_periods'] > 0
            )
            ->values()
            ->all();
    }

    /**
     * Estimate scheduling difficulty for sorting.
     */
    protected function candidateDifficulty(
        array $candidate
    ): int {
        $score = (int) ($candidate['priority'] ?? 1) * 10;

        if (!empty($candidate['triple_period'])) {
            $score += 100;
        }

        if (!empty($candidate['double_period'])) {
            $score += 50;
        }

        if (!empty($candidate['preferred_periods'])) {
            $score += 30;
        }

        if (!empty($candidate['preferred_weekdays'])) {
            $score += 30;
        }

        if (!empty($candidate['one_period_per_day'])) {
            $score += 20;
        }

        $score += (int) (
            $candidate['weekly_periods'] ?? 0
        );

        return $score;
    }

    /**
     * Validate required engine parameters.
     */
    protected function validateParameters(
        array $parameters
    ): void {
        $required = [
            'subscription_id',
            'academic_year_id',
            'grade_id',
            'section_id',
        ];

        foreach ($required as $field) {
            if (
                !isset($parameters[$field]) ||
                !is_numeric($parameters[$field]) ||
                (int) $parameters[$field] < 1
            ) {
                throw new InvalidArgumentException(
                    "The {$field} parameter is required."
                );
            }
        }

        $workingDays = (int) (
            $parameters['working_days'] ?? 6
        );

        $periodsPerDay = (int) (
            $parameters['periods_per_day'] ?? 8
        );

        if ($workingDays < 1 || $workingDays > 7) {
            throw new InvalidArgumentException(
                'Working days must be between 1 and 7.'
            );
        }

        if ($periodsPerDay < 1 || $periodsPerDay > 20) {
            throw new InvalidArgumentException(
                'Periods per day must be between 1 and 20.'
            );
        }
    }

    public function grid(): ?ScheduleGrid
    {
        return $this->generatedGrid;
    }

    public function statistics(): array
    {
        return $this->statistics;
    }
}