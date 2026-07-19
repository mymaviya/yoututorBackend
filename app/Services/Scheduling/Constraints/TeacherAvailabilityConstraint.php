<?php

namespace App\Services\Scheduling\Constraints;

use App\Models\TeacherAvailability;
use App\Models\TeacherAvailabilityException;
use App\Services\Scheduling\ConstraintInterface;
use App\Services\Scheduling\ScheduleGrid;

class TeacherAvailabilityConstraint implements ConstraintInterface
{
    public function passes(
        ScheduleGrid $grid,
        array $candidate,
        int $weekday,
        int $periodNo
    ): bool {

        // Check whether teacher is already allocated in this timetable
        $slot = $grid->get($weekday, $periodNo);

        if (
            $slot &&
            !empty($slot['teacher_id']) &&
            $slot['teacher_id'] == $candidate['teacher_id']
        ) {
            return false;
        }

        /*
        |--------------------------------------------------------------------------
        | Weekly Availability
        |--------------------------------------------------------------------------
        */

        $availability = TeacherAvailability::query()
            ->where('teacher_id', $candidate['teacher_id'])
            ->where('weekday', $weekday)
            ->where('period_no', $periodNo)
            ->first();

        if ($availability) {

            if ($availability->status === 'unavailable') {
                return false;
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Date Exception (Leave / Holiday / Exam Duty etc.)
        |--------------------------------------------------------------------------
        |
        | This is validated only when the scheduler provides
        | schedule_date in the candidate array.
        |
        */

        if (!empty($candidate['schedule_date'])) {

            $exception = TeacherAvailabilityException::query()
                ->where('teacher_id', $candidate['teacher_id'])
                ->whereDate('date', $candidate['schedule_date'])
                ->where('period_no', $periodNo)
                ->first();

            if ($exception) {

                if ($exception->status === 'unavailable') {
                    return false;
                }
            }
        }

        return true;
    }

    public function message(): string
    {
        return 'Teacher is unavailable during the selected period.';
    }

    public function penalty(): int
    {
        // Hard Constraint
        return 1000000;
    }
}