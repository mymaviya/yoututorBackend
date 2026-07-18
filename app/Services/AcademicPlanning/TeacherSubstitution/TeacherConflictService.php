<?php

namespace App\Services\AcademicPlanning\TeacherSubstitution;

use App\Models\TeacherAvailabilityException;
use App\Models\TeacherSubstitution;
use App\Models\TimetableEntry;
use Carbon\Carbon;

class TeacherConflictService
{
    public function hasAvailabilityConflict(
        ?int $subscriptionId,
        int $teacherId,
        string|Carbon $date,
        int $bellId
    ): bool {
        $date = Carbon::parse($date);

        return TeacherAvailabilityException::query()
            ->when($subscriptionId, fn ($query) => $query->where('subscription_id', $subscriptionId))
            ->where('teacher_id', $teacherId)
            ->whereDate('exception_date', $date)
            ->where('is_active', true)
            ->where(function ($query) use ($bellId) {
                $query->where('is_full_day', true)
                    ->orWhere('school_bell_id', $bellId);
            })
            ->exists();
    }

    public function hasTimetableConflict(
        int $academicYearId,
        int $teacherId,
        string|Carbon $date,
        int $bellId
    ): bool {
        $date = Carbon::parse($date);

        return TimetableEntry::query()
            ->where('teacher_id', $teacherId)
            ->where('weekday', strtolower($date->format('l')))
            ->where('school_bell_id', $bellId)
            ->whereHas('weeklyTimetable', function ($query) use ($academicYearId) {
                $query->where('academic_year_id', $academicYearId);
            })
            ->exists();
    }

    public function hasSubstitutionConflict(
        int $teacherId,
        string|Carbon $date,
        int $bellId
    ): bool {
        $date = Carbon::parse($date);

        return TeacherSubstitution::query()
            ->whereDate('substitution_date', $date)
            ->where('substitute_teacher_id', $teacherId)
            ->whereNotIn('status', ['rejected'])
            ->whereHas('timetableEntry', function ($query) use ($bellId) {
                $query->where('school_bell_id', $bellId);
            })
            ->exists();
    }

    public function conflicts(
        ?int $subscriptionId,
        int $academicYearId,
        int $teacherId,
        string|Carbon $date,
        int $bellId
    ): array {
        $conflicts = [];

        if ($this->hasAvailabilityConflict($subscriptionId, $teacherId, $date, $bellId)) {
            $conflicts[] = 'Teacher is marked unavailable for this period.';
        }

        if ($this->hasTimetableConflict($academicYearId, $teacherId, $date, $bellId)) {
            $conflicts[] = 'Teacher already has a timetable class in this period.';
        }

        if ($this->hasSubstitutionConflict($teacherId, $date, $bellId)) {
            $conflicts[] = 'Teacher is already assigned as substitute in this period.';
        }

        return $conflicts;
    }

    public function hasAnyConflict(
        ?int $subscriptionId,
        int $academicYearId,
        int $teacherId,
        string|Carbon $date,
        int $bellId
    ): bool {
        return count($this->conflicts($subscriptionId, $academicYearId, $teacherId, $date, $bellId)) > 0;
    }
}
