<?php

namespace App\Services\TeacherAvailability;

use App\Models\SchoolBell;
use App\Models\TeacherAvailability;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TeacherAvailabilityService
{
    /**
     * Return weekly availability for a teacher.
     *
     * The academic year argument is retained for API compatibility. Weekly
     * availability is currently stored independently of an academic year.
     */
    public function getWeeklyAvailability(
        int $subscriptionId,
        int $academicYearId,
        int $teacherId
    ): Collection {
        return TeacherAvailability::query()
            ->where('subscription_id', $subscriptionId)
            ->where('teacher_id', $teacherId)
            ->with('bell')
            ->ordered()
            ->get();
    }

    /**
     * Save the complete weekly availability grid for a teacher.
     */
    public function saveWeeklyAvailability(
        int $subscriptionId,
        int $academicYearId,
        int $teacherId,
        array $rows
    ): Collection {
        $bellsByPeriod = $this->teachingBellsByPeriod();

        DB::transaction(function () use (
            $subscriptionId,
            $teacherId,
            $rows,
            $bellsByPeriod
        ): void {
            TeacherAvailability::query()
                ->where('subscription_id', $subscriptionId)
                ->where('teacher_id', $teacherId)
                ->delete();

            foreach ($rows as $row) {
                $this->validateRow($row, $bellsByPeriod);

                $periodNo = (int) $row['period_no'];
                $bell = $bellsByPeriod->get($periodNo);

                TeacherAvailability::create([
                    'subscription_id' => $subscriptionId,
                    'teacher_id' => $teacherId,
                    'weekday' => (int) $row['weekday'],
                    'school_bell_id' => $bell->id,
                    'status' => strtolower((string) $row['status']),
                    'reason' => $row['reason'] ?? null,
                    'is_active' => true,
                ]);
            }
        });

        return $this->getWeeklyAvailability(
            $subscriptionId,
            $academicYearId,
            $teacherId
        );
    }

    /**
     * Copy availability from one teacher to another.
     */
    public function copyAvailability(
        int $subscriptionId,
        int $academicYearId,
        int $sourceTeacherId,
        int $destinationTeacherId
    ): Collection {
        if ($sourceTeacherId === $destinationTeacherId) {
            throw ValidationException::withMessages([
                'destination_teacher_id' => 'Source and destination teacher cannot be the same.',
            ]);
        }

        $records = $this->getWeeklyAvailability(
            $subscriptionId,
            $academicYearId,
            $sourceTeacherId
        );

        DB::transaction(function () use (
            $subscriptionId,
            $destinationTeacherId,
            $records
        ): void {
            TeacherAvailability::query()
                ->where('subscription_id', $subscriptionId)
                ->where('teacher_id', $destinationTeacherId)
                ->delete();

            foreach ($records as $record) {
                TeacherAvailability::create([
                    'subscription_id' => $subscriptionId,
                    'teacher_id' => $destinationTeacherId,
                    'weekday' => $record->weekday,
                    'school_bell_id' => $record->school_bell_id,
                    'status' => $record->status,
                    'reason' => $record->reason,
                    'is_active' => $record->is_active,
                ]);
            }
        });

        return $this->getWeeklyAvailability(
            $subscriptionId,
            $academicYearId,
            $destinationTeacherId
        );
    }

    /**
     * Check whether a teacher is available for a timetable slot.
     */
    public function isTeacherAvailable(
        int $subscriptionId,
        int $academicYearId,
        int $teacherId,
        int $weekday,
        int $periodNo
    ): bool {
        $bellId = $this->teachingBellsByPeriod()
            ->get($periodNo)?->id;

        if (!$bellId) {
            return false;
        }

        $availability = TeacherAvailability::query()
            ->where('subscription_id', $subscriptionId)
            ->where('teacher_id', $teacherId)
            ->where('weekday', $weekday)
            ->where('school_bell_id', $bellId)
            ->where('is_active', true)
            ->first();

        if (!$availability) {
            return true;
        }

        return in_array(
            strtolower((string) $availability->status),
            ['available', 'preferred'],
            true
        );
    }

    /**
     * Reset complete weekly availability.
     */
    public function resetAvailability(
        int $subscriptionId,
        int $academicYearId,
        int $teacherId
    ): void {
        TeacherAvailability::query()
            ->where('subscription_id', $subscriptionId)
            ->where('teacher_id', $teacherId)
            ->delete();
    }

    /**
     * Create default availability for all requested teaching slots.
     */
    public function createDefaultAvailability(
        int $subscriptionId,
        int $academicYearId,
        int $teacherId,
        int $workingDays,
        int $periodsPerDay
    ): Collection {
        $bells = $this->teachingBellsByPeriod()
            ->take($periodsPerDay);

        if ($bells->count() < $periodsPerDay) {
            throw ValidationException::withMessages([
                'periods_per_day' => 'The school bell schedule does not contain enough active teaching periods.',
            ]);
        }

        DB::transaction(function () use (
            $subscriptionId,
            $teacherId,
            $workingDays,
            $bells
        ): void {
            TeacherAvailability::query()
                ->where('subscription_id', $subscriptionId)
                ->where('teacher_id', $teacherId)
                ->delete();

            for ($day = 1; $day <= $workingDays; $day++) {
                foreach ($bells as $bell) {
                    TeacherAvailability::create([
                        'subscription_id' => $subscriptionId,
                        'teacher_id' => $teacherId,
                        'weekday' => $day,
                        'school_bell_id' => $bell->id,
                        'status' => 'available',
                        'reason' => null,
                        'is_active' => true,
                    ]);
                }
            }
        });

        return $this->getWeeklyAvailability(
            $subscriptionId,
            $academicYearId,
            $teacherId
        );
    }

    /**
     * Validate one availability row.
     */
    protected function validateRow(array $row, Collection $bellsByPeriod): void
    {
        if (
            !array_key_exists('weekday', $row)
            || !array_key_exists('period_no', $row)
            || !array_key_exists('status', $row)
        ) {
            throw ValidationException::withMessages([
                'availability' => 'Invalid availability row.',
            ]);
        }

        $weekday = (int) $row['weekday'];
        $periodNo = (int) $row['period_no'];
        $status = strtolower((string) $row['status']);

        if ($weekday < 1 || $weekday > 7) {
            throw ValidationException::withMessages([
                'availability' => 'Weekday must be between 1 and 7.',
            ]);
        }

        if (!$bellsByPeriod->has($periodNo)) {
            throw ValidationException::withMessages([
                'availability' => "Teaching period {$periodNo} does not exist in the school bell schedule.",
            ]);
        }

        if (!in_array($status, ['available', 'unavailable', 'preferred'], true)) {
            throw ValidationException::withMessages([
                'availability' => 'Invalid availability status.',
            ]);
        }
    }

    /**
     * Return active teaching bells keyed by period number.
     */
    protected function teachingBellsByPeriod(): Collection
    {
        return SchoolBell::query()
            ->active()
            ->teachingPeriods()
            ->whereNotNull('period_number')
            ->ordered()
            ->get()
            ->keyBy(fn (SchoolBell $bell): int => (int) $bell->period_number);
    }
}
