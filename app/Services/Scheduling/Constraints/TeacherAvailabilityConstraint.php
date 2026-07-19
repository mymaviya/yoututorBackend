<?php

namespace App\Services\Scheduling\Constraints;

use App\Models\SchoolBell;
use App\Models\TeacherAvailability;
use App\Models\TeacherAvailabilityException;
use App\Services\Scheduling\ConstraintInterface;
use App\Services\Scheduling\ScheduleGrid;

class TeacherAvailabilityConstraint implements ConstraintInterface
{
    /**
     * Cache period number to school bell ID mappings during one request.
     *
     * @var array<int, int|null>
     */
    private array $bellIds = [];

    public function passes(
        ScheduleGrid $grid,
        array $candidate,
        int $weekday,
        int $periodNo
    ): bool {
        $teacherId = (int) ($candidate['teacher_id'] ?? 0);

        if ($teacherId <= 0) {
            return false;
        }

        $bellId = $this->resolveBellId($periodNo);

        if ($bellId === null) {
            return false;
        }

        $availabilityQuery = TeacherAvailability::query()
            ->where('teacher_id', $teacherId)
            ->where('weekday', $weekday)
            ->where('school_bell_id', $bellId)
            ->where('is_active', true);

        if (!empty($candidate['subscription_id'])) {
            $availabilityQuery->where(
                'subscription_id',
                (int) $candidate['subscription_id']
            );
        }

        $availability = $availabilityQuery->first();

        if (
            $availability
            && in_array(
                strtolower((string) $availability->status),
                ['unavailable', 'busy', 'leave', 'meeting', 'blocked'],
                true
            )
        ) {
            return false;
        }

        if (!empty($candidate['schedule_date'])) {
            $exceptionQuery = TeacherAvailabilityException::query()
                ->active()
                ->forTeacher($teacherId)
                ->forDate($candidate['schedule_date'])
                ->forBell($bellId);

            if (!empty($candidate['subscription_id'])) {
                $exceptionQuery->where(
                    'subscription_id',
                    (int) $candidate['subscription_id']
                );
            }

            if (!empty($candidate['academic_year_id'])) {
                $exceptionQuery->where(function ($query) use ($candidate) {
                    $query
                        ->whereNull('academic_year_id')
                        ->orWhere(
                            'academic_year_id',
                            (int) $candidate['academic_year_id']
                        );
                });
            }

            $exception = $exceptionQuery->first();

            if ($exception && $exception->blocksRegularTeaching()) {
                return false;
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
        return 1000000;
    }

    private function resolveBellId(int $periodNo): ?int
    {
        if (array_key_exists($periodNo, $this->bellIds)) {
            return $this->bellIds[$periodNo];
        }

        $bellId = SchoolBell::query()
            ->active()
            ->teachingPeriods()
            ->where('period_number', $periodNo)
            ->value('id');

        return $this->bellIds[$periodNo] = $bellId !== null
            ? (int) $bellId
            : null;
    }
}
