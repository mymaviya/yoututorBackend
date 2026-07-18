<?php

namespace App\Services\AcademicPlanning\TeacherSubstitution;

use App\Models\TeacherSubstitution;
use App\Models\TimetableEntry;
use Carbon\Carbon;

class TeacherWorkloadService
{
    public function todayLoad(int $teacherId, int $academicYearId, string|Carbon $date): int
    {
        $date = Carbon::parse($date);

        return TimetableEntry::query()
            ->where('teacher_id', $teacherId)
            ->where('weekday', strtolower($date->format('l')))
            ->whereHas('weeklyTimetable', function ($query) use ($academicYearId) {
                $query->where('academic_year_id', $academicYearId);
            })
            ->count();
    }

    public function weeklyLoad(int $teacherId, int $academicYearId, string|Carbon $date): int
    {
        return TimetableEntry::query()
            ->where('teacher_id', $teacherId)
            ->whereIn('weekday', ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'])
            ->whereHas('weeklyTimetable', function ($query) use ($academicYearId) {
                $query->where('academic_year_id', $academicYearId);
            })
            ->count();
    }

    public function monthlySubstitutions(int $teacherId, string|Carbon $date): int
    {
        $date = Carbon::parse($date);

        return TeacherSubstitution::query()
            ->where('substitute_teacher_id', $teacherId)
            ->whereMonth('substitution_date', $date->month)
            ->whereYear('substitution_date', $date->year)
            ->whereNotIn('status', ['rejected'])
            ->count();
    }

    public function dailySubstitutions(int $teacherId, string|Carbon $date): int
    {
        $date = Carbon::parse($date);

        return TeacherSubstitution::query()
            ->where('substitute_teacher_id', $teacherId)
            ->whereDate('substitution_date', $date)
            ->whereNotIn('status', ['rejected'])
            ->count();
    }

    public function workloadScore(int $teacherId, int $academicYearId, string|Carbon $date): float
    {
        $todayLoad = $this->todayLoad($teacherId, $academicYearId, $date);
        $dailySubs = $this->dailySubstitutions($teacherId, $date);
        $monthlySubs = $this->monthlySubstitutions($teacherId, $date);

        $score = 20;
        $score -= min($todayLoad * 1.5, 12);
        $score -= min($dailySubs * 3, 6);
        $score -= min($monthlySubs * 0.5, 8);

        return max(round($score, 2), 0);
    }

    public function summary(int $teacherId, int $academicYearId, string|Carbon $date): array
    {
        return [
            'today' => $this->todayLoad($teacherId, $academicYearId, $date),
            'weekly' => $this->weeklyLoad($teacherId, $academicYearId, $date),
            'daily_substitutions' => $this->dailySubstitutions($teacherId, $date),
            'monthly_substitutions' => $this->monthlySubstitutions($teacherId, $date),
            'workload_score' => $this->workloadScore($teacherId, $academicYearId, $date),
        ];
    }
}
