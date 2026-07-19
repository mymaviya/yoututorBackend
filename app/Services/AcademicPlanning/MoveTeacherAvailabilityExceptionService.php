<?php

namespace App\Services\AcademicPlanning;

use App\Models\TeacherAvailabilityException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MoveTeacherAvailabilityExceptionService
{
    /**
     * Move a period-specific availability exception to another date and bell.
     *
     * @param  array{exception_date:string,weekday:int,school_bell_id:int}  $data
     */
    public function move(
        TeacherAvailabilityException $exception,
        array $data
    ): TeacherAvailabilityException {
        return DB::transaction(function () use ($exception, $data) {
            /** @var TeacherAvailabilityException $lockedException */
            $lockedException = TeacherAvailabilityException::query()
                ->whereKey($exception->getKey())
                ->where('subscription_id', $exception->subscription_id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedException->is_full_day) {
                throw ValidationException::withMessages([
                    'school_bell_id' => 'A full-day exception cannot be moved to a single bell. Update the exception instead.',
                ]);
            }

            $hasConflict = TeacherAvailabilityException::query()
                ->where('subscription_id', $lockedException->subscription_id)
                ->where('teacher_id', $lockedException->teacher_id)
                ->whereDate('exception_date', $data['exception_date'])
                ->where('is_active', true)
                ->whereKeyNot($lockedException->getKey())
                ->where(function ($query) use ($data) {
                    $query
                        ->where('is_full_day', true)
                        ->orWhere('school_bell_id', $data['school_bell_id']);
                })
                ->exists();

            if ($hasConflict) {
                throw ValidationException::withMessages([
                    'school_bell_id' => 'This teacher already has an active exception for the selected date and bell.',
                ]);
            }

            $lockedException->update([
                'exception_date' => $data['exception_date'],
                'weekday' => (int) $data['weekday'],
                'school_bell_id' => (int) $data['school_bell_id'],
            ]);

            return $lockedException->refresh();
        });
    }
}
