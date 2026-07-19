<?php

namespace App\Services\AcademicPlanning\TeacherSubstitution;

use App\Models\TeacherSubstitution;
use App\Models\TimetableEntry;
use Carbon\Carbon;

class TeacherWorkloadService
{
    public function todayLoad(
        int $subscriptionId,
        int $teacherId,
        int $academicYearId,
        string|Carbon $date
    ): int {
        $date = Carbon::parse($date);

        return TimetableEntry::query()
            ->active()
            ->forSubscription($subscriptionId)
            ->forTeacher($teacherId)
            ->where('weekday', strtolower($date->format('l')))
            ->whereHas(
                'weeklyTimetable',
                fn ($query) => $query->where('academic_year_id', $academicYearId)
            )
            ->count();
    }

    public function weeklyLoad(
        int $subscriptionId,
        int $teacherId,
        int $academicYearId
    ): int {
        return TimetableEntry::query()
            ->active()
            ->forSubscription($subscriptionId)
            ->forTeacher($teacherId)
            ->whereIn('weekday', [
                'monday',
                'tuesday',
                'wednesday',
                'thursday',
                'friday',
                'saturday',
                'sunday',
            ])
            ->whereHas(
                'weeklyTimetable',
                fn ($query) => $query->where('academic_year_id', $academicYearId)
            )
            ->count();
    }

    public function monthlySubstitutions(
        int $subscriptionId,
        int $teacherId,
        string|Carbon $date
    ): int {
        $date = Carbon::parse($date);

        return TeacherSubstitution::query()
            ->where('subscription_id', $subscriptionId)
            ->where('substitute_teacher_id', $teacherId)
            ->whereMonth('substitution_date', $date->month)
            ->whereYear('substitution_date', $date->year)
            ->whereNotIn('status', [TeacherSubstitution::STATUS_REJECTED])
            ->count();
    }

    public function dailySubstitutions(
        int $subscriptionId,
        int $teacherId,
        string|Carbon $date
    ): int {
        $date = Carbon::parse($date);

        return TeacherSubstitution::query()
            ->where('subscription_id', $subscriptionId)
            ->where('substitute_teacher_id', $teacherId)
            ->whereDate('substitution_date', $date)
            ->whereNotIn('status', [TeacherSubstitution::STATUS_REJECTED])
            ->count();
    }

    public function workloadScore(
        int $subscriptionId,
        int $teacherId,
        int $academicYearId,
        string|Carbon $date
    ): float {
        $todayLoad = $this->todayLoad(
            $subscriptionId,
            $teacherId,
            $academicYearId,
            $date
        );
        $dailySubs = $this->dailySubstitutions(
            $subscriptionId,
            $teacherId,
            $date
        );
        $monthlySubs = $this->monthlySubstitutions(
            $subscriptionId,
            $teacherId,
            $date
        );

        $score = 20;
        $score -= min($todayLoad * 1.5, 12);
        $score -= min($dailySubs * 3, 6);
        $score -= min($monthlySubs * 0.5, 8);

        return max(round($score, 2), 0);
    }

    public function summary(
        int $subscriptionId,
        int $teacherId,
        int $academicYearId,
        string|Carbon $date
    ): array {
        return [
            'today' => $this->todayLoad(
                $subscriptionId,
                $teacherId,
                $academicYearId,
                $date
            ),
            'weekly' => $this->weeklyLoad(
                $subscriptionId,
                $teacherId,
                $academicYearId
            ),
            'daily_substitutions' => $this->dailySubstitutions(
                $subscriptionId,
                $teacherId,
                $date
            ),
            'monthly_substitutions' => $this->monthlySubstitutions(
                $subscriptionId,
                $teacherId,
                $date
            ),
            'workload_score' => $this->workloadScore(
                $subscriptionId,
                $teacherId,
                $academicYearId,
                $date
            ),
        ];
    }
}
