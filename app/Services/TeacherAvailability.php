<?php

namespace App\Services\TeacherAvailability;

use App\Models\TeacherAvailability;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TeacherAvailabilityService
{
    /**
     * Return weekly availability for a teacher.
     */
    public function getWeeklyAvailability(
        int $subscriptionId,
        int $academicYearId,
        int $teacherId
    ): Collection {
        return TeacherAvailability::query()
            ->where('subscription_id', $subscriptionId)
            ->where('academic_year_id', $academicYearId)
            ->where('teacher_id', $teacherId)
            ->ordered()
            ->get();
    }

    /**
     * Save complete weekly availability.
     *
     * Expected payload:
     *
     * [
     *   [
     *      'weekday' => 1,
     *      'period_no' => 1,
     *      'status' => 'available',
     *      'reason_type' => null,
     *      'reason' => null,
     *   ]
     * ]
     */
    public function saveWeeklyAvailability(
        int $subscriptionId,
        int $academicYearId,
        int $teacherId,
        array $rows
    ): Collection {

        DB::transaction(function () use (
            $subscriptionId,
            $academicYearId,
            $teacherId,
            $rows
        ) {

            TeacherAvailability::query()
                ->where('subscription_id', $subscriptionId)
                ->where('academic_year_id', $academicYearId)
                ->where('teacher_id', $teacherId)
                ->delete();

            foreach ($rows as $row) {

                $this->validateRow($row);

                TeacherAvailability::create([
                    'subscription_id' => $subscriptionId,
                    'academic_year_id' => $academicYearId,
                    'teacher_id' => $teacherId,

                    'weekday' => $row['weekday'],
                    'period_no' => $row['period_no'],

                    'status' => $row['status'],

                    'reason_type' => $row['reason_type'] ?? null,
                    'reason' => $row['reason'] ?? null,
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
                'teacher' => 'Source and destination teacher cannot be the same.',
            ]);
        }

        $records = $this->getWeeklyAvailability(
            $subscriptionId,
            $academicYearId,
            $sourceTeacherId
        );

        DB::transaction(function () use (
            $subscriptionId,
            $academicYearId,
            $destinationTeacherId,
            $records
        ) {

            TeacherAvailability::query()
                ->where('subscription_id', $subscriptionId)
                ->where('academic_year_id', $academicYearId)
                ->where('teacher_id', $destinationTeacherId)
                ->delete();

            foreach ($records as $record) {

                TeacherAvailability::create([
                    'subscription_id' => $subscriptionId,
                    'academic_year_id' => $academicYearId,
                    'teacher_id' => $destinationTeacherId,

                    'weekday' => $record->weekday,
                    'period_no' => $record->period_no,

                    'status' => $record->status,

                    'reason_type' => $record->reason_type,
                    'reason' => $record->reason,
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
     * Check whether a teacher is available for a slot.
     */
    public function isTeacherAvailable(
        int $subscriptionId,
        int $academicYearId,
        int $teacherId,
        int $weekday,
        int $periodNo
    ): bool {

        $availability = TeacherAvailability::query()
            ->where('subscription_id', $subscriptionId)
            ->where('academic_year_id', $academicYearId)
            ->where('teacher_id', $teacherId)
            ->where('weekday', $weekday)
            ->where('period_no', $periodNo)
            ->first();

        if (!$availability) {
            return true;
        }

        return in_array(
            strtolower($availability->status),
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
            ->where('academic_year_id', $academicYearId)
            ->where('teacher_id', $teacherId)
            ->delete();
    }

    /**
     * Create default availability for all slots.
     */
    public function createDefaultAvailability(
        int $subscriptionId,
        int $academicYearId,
        int $teacherId,
        int $workingDays,
        int $periodsPerDay
    ): Collection {

        DB::transaction(function () use (
            $subscriptionId,
            $academicYearId,
            $teacherId,
            $workingDays,
            $periodsPerDay
        ) {

            TeacherAvailability::query()
                ->where('subscription_id', $subscriptionId)
                ->where('academic_year_id', $academicYearId)
                ->where('teacher_id', $teacherId)
                ->delete();

            for ($day = 1; $day <= $workingDays; $day++) {

                for ($period = 1; $period <= $periodsPerDay; $period++) {

                    TeacherAvailability::create([
                        'subscription_id' => $subscriptionId,
                        'academic_year_id' => $academicYearId,
                        'teacher_id' => $teacherId,

                        'weekday' => $day,
                        'period_no' => $period,

                        'status' => 'available',
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
    protected function validateRow(array $row): void
    {
        if (
            !isset($row['weekday']) ||
            !isset($row['period_no']) ||
            !isset($row['status'])
        ) {
            throw ValidationException::withMessages([
                'availability' => 'Invalid availability row.',
            ]);
        }

        if (
            !in_array(
                strtolower($row['status']),
                [
                    'available',
                    'unavailable',
                    'preferred',
                ],
                true
            )
        ) {
            throw ValidationException::withMessages([
                'status' => 'Invalid availability status.',
            ]);
        }
    }
}