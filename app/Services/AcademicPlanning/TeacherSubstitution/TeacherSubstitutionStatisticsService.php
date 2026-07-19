<?php

namespace App\Services\AcademicPlanning\TeacherSubstitution;

use App\Models\TeacherSubstitution;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class TeacherSubstitutionStatisticsService
{
    public function dashboard(
        int $subscriptionId,
        ?int $academicYearId,
        string|Carbon $date
    ): array {
        $date = Carbon::parse($date);

        $baseQuery = $this->baseQuery($subscriptionId, $academicYearId)
            ->whereDate('substitution_date', $date);

        $total = (clone $baseQuery)->count();
        $pending = (clone $baseQuery)
            ->where('status', TeacherSubstitution::STATUS_PENDING)
            ->count();
        $approved = (clone $baseQuery)
            ->where('status', TeacherSubstitution::STATUS_APPROVED)
            ->count();
        $completed = (clone $baseQuery)
            ->where('status', TeacherSubstitution::STATUS_COMPLETED)
            ->count();
        $rejected = (clone $baseQuery)
            ->where('status', TeacherSubstitution::STATUS_REJECTED)
            ->count();

        $autoAssigned = (clone $baseQuery)
            ->where('is_ai_suggested', true)
            ->whereNotNull('substitute_teacher_id')
            ->count();

        $manualAssigned = (clone $baseQuery)
            ->where(function (Builder $query) {
                $query
                    ->where('is_ai_suggested', false)
                    ->orWhereNull('is_ai_suggested');
            })
            ->whereNotNull('substitute_teacher_id')
            ->count();

        $covered = $approved + $completed;

        return [
            'summary' => [
                'total' => $total,
                'pending' => $pending,

                // Frontend compatibility aliases.
                'assigned' => $approved,
                'cancelled' => $rejected,

                // Database-native statuses.
                'approved' => $approved,
                'rejected' => $rejected,
                'completed' => $completed,

                'covered' => $covered,
                'coverage_percentage' => $total > 0
                    ? round(($covered / $total) * 100, 2)
                    : 0.0,
            ],
            'ai' => [
                'auto_assigned' => $autoAssigned,
                'manual_assigned' => $manualAssigned,
                'average_score' => round(
                    (float) (clone $baseQuery)
                        ->whereNotNull('ai_score')
                        ->avg('ai_score'),
                    2
                ),
            ],
            'status' => [
                'pending' => $pending,

                // Frontend compatibility aliases.
                'assigned' => $approved,
                'cancelled' => $rejected,

                // Database-native statuses.
                'approved' => $approved,
                'rejected' => $rejected,
                'completed' => $completed,
            ],
            'teacher_load' => $this->teacherLoad(
                $subscriptionId,
                $academicYearId,
                $date
            ),
            'subject_analysis' => $this->subjectAnalysis(
                $subscriptionId,
                $academicYearId,
                $date
            ),
            'grade_analysis' => $this->gradeAnalysis(
                $subscriptionId,
                $academicYearId,
                $date
            ),
            'monthly_trend' => $this->monthlyTrend(
                $subscriptionId,
                $academicYearId,
                $date
            ),
            'weekday_heatmap' => $this->weekdayHeatmap(
                $subscriptionId,
                $academicYearId,
                $date
            ),
        ];
    }

    private function teacherLoad(
        int $subscriptionId,
        ?int $academicYearId,
        Carbon $date
    ): array {
        return $this->baseQuery($subscriptionId, $academicYearId)
            ->select('substitute_teacher_id', DB::raw('COUNT(*) as total'))
            ->with('substituteTeacher:id,name,email')
            ->whereMonth('substitution_date', $date->month)
            ->whereYear('substitution_date', $date->year)
            ->whereNotNull('substitute_teacher_id')
            ->where('status', '!=', TeacherSubstitution::STATUS_REJECTED)
            ->groupBy('substitute_teacher_id')
            ->orderByDesc('total')
            ->limit(10)
            ->get()
            ->map(fn (TeacherSubstitution $row) => [
                'teacher' => $row->substituteTeacher,
                'total' => (int) $row->total,
            ])
            ->values()
            ->toArray();
    }

    private function subjectAnalysis(
        int $subscriptionId,
        ?int $academicYearId,
        Carbon $date
    ): array {
        return $this->baseQuery($subscriptionId, $academicYearId)
            ->select('subject_id', DB::raw('COUNT(*) as total'))
            ->with('subject:id,name')
            ->whereMonth('substitution_date', $date->month)
            ->whereYear('substitution_date', $date->year)
            ->whereNotNull('subject_id')
            ->where('status', '!=', TeacherSubstitution::STATUS_REJECTED)
            ->groupBy('subject_id')
            ->orderByDesc('total')
            ->get()
            ->map(fn (TeacherSubstitution $row) => [
                'subject' => $row->subject,
                'total' => (int) $row->total,
            ])
            ->values()
            ->toArray();
    }

    private function gradeAnalysis(
        int $subscriptionId,
        ?int $academicYearId,
        Carbon $date
    ): array {
        return $this->baseQuery($subscriptionId, $academicYearId)
            ->select('grade_id', DB::raw('COUNT(*) as total'))
            ->with('grade:id,name')
            ->whereMonth('substitution_date', $date->month)
            ->whereYear('substitution_date', $date->year)
            ->whereNotNull('grade_id')
            ->where('status', '!=', TeacherSubstitution::STATUS_REJECTED)
            ->groupBy('grade_id')
            ->orderByDesc('total')
            ->get()
            ->map(fn (TeacherSubstitution $row) => [
                'grade' => $row->grade,
                'total' => (int) $row->total,
            ])
            ->values()
            ->toArray();
    }

    private function monthlyTrend(
        int $subscriptionId,
        ?int $academicYearId,
        Carbon $date
    ): array {
        return $this->baseQuery($subscriptionId, $academicYearId)
            ->select(
                DB::raw('MONTH(substitution_date) as month'),
                DB::raw('COUNT(*) as total')
            )
            ->whereYear('substitution_date', $date->year)
            ->where('status', '!=', TeacherSubstitution::STATUS_REJECTED)
            ->groupBy(DB::raw('MONTH(substitution_date)'))
            ->orderBy(DB::raw('MONTH(substitution_date)'))
            ->get()
            ->map(fn (TeacherSubstitution $row) => [
                'month' => (int) $row->month,
                'total' => (int) $row->total,
            ])
            ->values()
            ->toArray();
    }

    private function weekdayHeatmap(
        int $subscriptionId,
        ?int $academicYearId,
        Carbon $date
    ): array {
        return $this->baseQuery($subscriptionId, $academicYearId)
            ->select(
                DB::raw('DAYNAME(substitution_date) as weekday'),
                DB::raw('COUNT(*) as total')
            )
            ->whereMonth('substitution_date', $date->month)
            ->whereYear('substitution_date', $date->year)
            ->where('status', '!=', TeacherSubstitution::STATUS_REJECTED)
            ->groupBy(DB::raw('DAYNAME(substitution_date)'))
            ->get()
            ->map(fn (TeacherSubstitution $row) => [
                'weekday' => $row->weekday,
                'total' => (int) $row->total,
            ])
            ->values()
            ->toArray();
    }

    private function baseQuery(
        int $subscriptionId,
        ?int $academicYearId
    ): Builder {
        return TeacherSubstitution::query()
            ->where('subscription_id', $subscriptionId)
            ->when(
                $academicYearId !== null,
                fn (Builder $query) => $query->where(
                    'academic_year_id',
                    $academicYearId
                )
            );
    }
}
