<?php

namespace App\Services\AcademicPlanning\TeacherSubstitution;

use App\Models\TeacherAvailabilityException;
use Illuminate\Validation\ValidationException;

class MoveTeacherAvailabilityExceptionService
{
    public function move(
        TeacherAvailabilityException $exception,
        array $data
    ): TeacherAvailabilityException {
        $this->ensureNoDuplicate($exception, $data);

        $exception->update([
            'exception_date' => $data['exception_date'],
            'weekday' => $data['weekday'],
            'school_bell_id' => $data['school_bell_id'],
        ]);

        return $exception->fresh([
            'teacher',
            'bell',
            'academicYear',
        ]);
    }

    private function ensureNoDuplicate(
        TeacherAvailabilityException $exception,
        array $data
    ): void {
        $exists = TeacherAvailabilityException::query()
            ->where('subscription_id', $exception->subscription_id)
            ->where('teacher_id', $exception->teacher_id)
            ->where('exception_date', $data['exception_date'])
            ->where('weekday', $data['weekday'])
            ->where('school_bell_id', $data['school_bell_id'])
            ->where('id', '!=', $exception->id)
            ->where('is_active', true)
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'school_bell_id' => [
                    'This teacher already has an exception in the selected period.',
                ],
            ]);
        }
    }
}