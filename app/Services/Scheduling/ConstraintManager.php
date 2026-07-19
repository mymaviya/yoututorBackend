<?php

namespace App\Services\Scheduling;

class ConstraintManager
{
    /**
     * @var ConstraintInterface[]
     */
    protected array $constraints = [];

    /**
     * Register a constraint.
     */
    public function add(
        ConstraintInterface $constraint
    ): self {
        $this->constraints[] = $constraint;

        return $this;
    }

    /**
     * Register multiple constraints.
     *
     * @param ConstraintInterface[] $constraints
     */
    public function addMany(
        array $constraints
    ): self {
        foreach ($constraints as $constraint) {
            $this->add($constraint);
        }

        return $this;
    }

    /**
     * Validate all hard constraints.
     */
    public function passes(
        ScheduleGrid $grid,
        array $candidate,
        int $weekday,
        int $periodNo
    ): bool {

        foreach ($this->constraints as $constraint) {

            if (!$constraint->passes(
                $grid,
                $candidate,
                $weekday,
                $periodNo
            )) {
                return false;
            }
        }

        return true;
    }

    /**
     * Return failed constraint messages.
     */
    public function messages(
        ScheduleGrid $grid,
        array $candidate,
        int $weekday,
        int $periodNo
    ): array {

        $messages = [];

        foreach ($this->constraints as $constraint) {

            if (!$constraint->passes(
                $grid,
                $candidate,
                $weekday,
                $periodNo
            )) {

                $messages[] = $constraint->message();
            }
        }

        return $messages;
    }

    /**
     * Calculate total penalty.
     */
    public function penalty(
        ScheduleGrid $grid,
        array $candidate,
        int $weekday,
        int $periodNo
    ): int {

        $penalty = 0;

        foreach ($this->constraints as $constraint) {

            if (!$constraint->passes(
                $grid,
                $candidate,
                $weekday,
                $periodNo
            )) {

                $penalty += $constraint->penalty();
            }
        }

        return $penalty;
    }

    /**
     * Return registered constraints.
     *
     * @return ConstraintInterface[]
     */
    public function all(): array
    {
        return $this->constraints;
    }

    /**
     * Remove all constraints.
     */
    public function clear(): self
    {
        $this->constraints = [];

        return $this;
    }

    /**
     * Count constraints.
     */
    public function count(): int
    {
        return count($this->constraints);
    }
}