<?php

namespace App\Services\Scheduling;

class ScheduleGrid
{
    protected array $grid = [];

    protected int $workingDays;

    protected int $periodsPerDay;

    public function __construct(
        int $workingDays,
        int $periodsPerDay
    ) {
        $this->workingDays = $workingDays;
        $this->periodsPerDay = $periodsPerDay;

        $this->initialize();
    }

    /**
     * Initialize empty timetable.
     */
    protected function initialize(): void
    {
        for ($day = 1; $day <= $this->workingDays; $day++) {

            for ($period = 1; $period <= $this->periodsPerDay; $period++) {

                $this->grid[$day][$period] = [
                    'weekday' => $day,
                    'period_no' => $period,

                    'teacher_id' => null,
                    'teacher_name' => null,

                    'subject_id' => null,
                    'subject_name' => null,

                    'grade_id' => null,
                    'section_id' => null,
                    'stream_id' => null,

                    'locked' => false,
                    'generated' => false,
                ];
            }
        }
    }

    /**
     * Get complete grid.
     */
    public function all(): array
    {
        return $this->grid;
    }

    /**
     * Get slot.
     */
    public function get(
        int $weekday,
        int $periodNo
    ): ?array {

        return $this->grid[$weekday][$periodNo] ?? null;
    }

    /**
     * Set slot.
     */
    public function set(
        int $weekday,
        int $periodNo,
        array $candidate
    ): void {

        $this->grid[$weekday][$periodNo] = array_merge(
            $this->grid[$weekday][$periodNo],
            [

                'teacher_id' => $candidate['teacher_id'],
                'teacher_name' => $candidate['teacher_name'],

                'subject_id' => $candidate['subject_id'],
                'subject_name' => $candidate['subject_name'],

                'grade_id' => $candidate['grade_id'],
                'section_id' => $candidate['section_id'],
                'stream_id' => $candidate['stream_id'],

                'generated' => true,
            ]
        );
    }

    /**
     * Remove slot.
     */
    public function clear(
        int $weekday,
        int $periodNo
    ): void {

        if ($this->isLocked($weekday, $periodNo)) {
            return;
        }

        $this->grid[$weekday][$periodNo] = array_merge(
            $this->grid[$weekday][$periodNo],
            [

                'teacher_id' => null,
                'teacher_name' => null,

                'subject_id' => null,
                'subject_name' => null,

                'grade_id' => null,
                'section_id' => null,
                'stream_id' => null,

                'generated' => false,
            ]
        );
    }

    /**
     * Check whether slot is empty.
     */
    public function isEmpty(
        int $weekday,
        int $periodNo
    ): bool {

        return $this->grid[$weekday][$periodNo]['subject_id'] === null;
    }

    /**
     * Lock slot.
     */
    public function lock(
        int $weekday,
        int $periodNo
    ): void {

        $this->grid[$weekday][$periodNo]['locked'] = true;
    }

    /**
     * Unlock slot.
     */
    public function unlock(
        int $weekday,
        int $periodNo
    ): void {

        $this->grid[$weekday][$periodNo]['locked'] = false;
    }

    /**
     * Check lock.
     */
    public function isLocked(
        int $weekday,
        int $periodNo
    ): bool {

        return (bool) $this->grid[$weekday][$periodNo]['locked'];
    }

    /**
     * Return all empty slots.
     */
    public function emptySlots(): array
    {
        $slots = [];

        foreach ($this->grid as $day => $periods) {

            foreach ($periods as $period => $slot) {

                if ($this->isEmpty($day, $period)) {

                    $slots[] = [
                        'weekday' => $day,
                        'period_no' => $period,
                    ];
                }
            }
        }

        return $slots;
    }

    /**
     * Count allocated slots.
     */
    public function allocatedCount(): int
    {
        $count = 0;

        foreach ($this->grid as $periods) {

            foreach ($periods as $slot) {

                if ($slot['subject_id']) {
                    $count++;
                }
            }
        }

        return $count;
    }

    /**
     * Count empty slots.
     */
    public function emptyCount(): int
    {
        return count($this->emptySlots());
    }
}