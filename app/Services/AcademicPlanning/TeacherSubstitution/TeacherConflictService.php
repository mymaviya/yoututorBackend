<?php

namespace App\Services\AcademicPlanning\TeacherSubstitution;

use App\Models\TeacherAvailabilityException;
use App\Models\TeacherSubstitution;
use App\Models\TimetableEntry;
use Carbon\Carbon;

class TeacherConflictService
{
    public function hasAvailabilityConflict(
        int $subscriptionId,
        int $teacherId,
        string|Carbon $date,
        int $bellId
    ): bool {
        $date = Carbon::parse($date);

        return TeacherAvailabilityException::query()
            ->where('subscription_id', $subscriptionId)
            ->where('teacher_id', $teacherId)
            ->whereDate('exception_date', $date)
            ->active()
            ->where(function ($query) use ($bellId) {
                $query->where('is_full_day', true)
                    ->orWhere('school_bell_id', $bellId);
            })
            ->whereIn('status', TeacherAvailabilityException::blockingStatuses())
            ->exists();
    }

    public function hasTimetableConflict(
        int $subscriptionId,
        int $academicYearId,
        int $teacherId,
        string|Carbon $date,
        int $bellId
    ): bool {
        $date = Carbon::parse($date);

        return TimetableEntry::query()
            ->active()
            ->forSubscription($subscriptionId)
            ->forTeacher($teacherId)
            ->where('weekday', strtolower($date->format('l')))
            ->where('school_bell_id', $bellId)
            ->whereHas(
                'weeklyTimetable',
                fn ($query) => $query->where('academic_year_id', $academicYearId)
            )
            ->exists();
    }

    public function hasSubstitutionConflict(
        int $subscriptionId,
        int $teacherId,
        string|Carbon $date,
        int $bellId
    ): bool {
        $date = Carbon::parse($date);

        return TeacherSubstitution::query()
            ->where('subscription_id', $subscriptionId)
            ->whereDate('substitution_date', $date)
            ->where('substitute_teacher_id', $teacherId)
            ->whereNotIn('status', [TeacherSubstitution::STATUS_REJECTED])
            ->whereHas(
                'timetableEntry',
                fn ($query) => $query
                    ->active()
                    ->where('school_bell_id', $bellId)
                    ->forSubscription($subscriptionId)
            )
            ->exists();
    }

    public function conflicts(
        int $subscriptionId,
        int $academicYearId,
        int $teacherId,
        string|Carbon $date,
        int $bellId
    ): array {
        $conflicts = [];

        if ($this->hasAvailabilityConflict($subscriptionId, $teacherId, $date, $bellId)) {
            $conflicts[] = 'Teacher is marked unavailable for this period.';
        }

        if ($this->hasTimetableConflict($subscriptionId, $academicYearId, $teacherId, $date, $bellId)) {
            $conflicts[] = 'Teacher already has a timetable class in this period.';
        }

        if ($this->hasSubstitutionConflict($subscriptionId, $teacherId, $date, $bellId)) {
            $conflicts[] = 'Teacher is already assigned as substitute in this period.';
        }

        return $conflicts;
    }

    public function hasAnyConflict(
        int $subscriptionId,
        int $academicYearId,
        int $teacherId,
        string|Carbon $date,
        int $bellId
    ): bool {
        return $this->conflicts(
            $subscriptionId,
            $academicYearId,
            $teacherId,
            $date,
            $bellId
        ) !== [];
    }
}
