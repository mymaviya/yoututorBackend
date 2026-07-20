<?php

namespace App\Services\AcademicPlanning;

use App\Models\AcademicYear;
use App\Models\BellScheduleSetting;
use App\Models\MotherTeacherSetting;
use App\Models\ParallelGroup;
use App\Models\Room;
use App\Models\SchoolBell;
use App\Models\SubjectPeriodAllocation;
use App\Models\TeacherAssignment;
use App\Models\TeacherAvailability;
use App\Models\TeacherSubstitution;
use App\Models\TeacherWorkloadSetting;
use App\Models\TimetableRule;
use App\Models\TimetableTemplate;
use App\Models\User;
use App\Models\WeeklyTimetable;
use Illuminate\Database\Eloquent\Builder;

class DashboardService
{
    public function dashboard(int $subscriptionId): array
    {
        $readiness = $this->readiness($subscriptionId);

        return [
            'statistics' => $this->statistics($subscriptionId),
            'readiness' => $readiness,
            'warnings' => $this->warningsFromChecks($readiness['checks']),
        ];
    }

    public function statistics(int $subscriptionId): array
    {
        return [
            'academic_years' => $this->tenantQuery(AcademicYear::class, $subscriptionId)->count(),
            'teachers' => User::query()
                ->teachers()
                ->where('subscription_id', $subscriptionId)
                ->count(),
            'teacher_assignments' => $this->tenantQuery(TeacherAssignment::class, $subscriptionId)->count(),
            'teacher_availability' => $this->tenantQuery(TeacherAvailability::class, $subscriptionId)->count(),
            'teacher_workload' => $this->tenantQuery(TeacherWorkloadSetting::class, $subscriptionId)->count(),
            'mother_teachers' => $this->tenantQuery(MotherTeacherSetting::class, $subscriptionId)->count(),
            'teacher_substitutions' => $this->tenantQuery(TeacherSubstitution::class, $subscriptionId)->count(),
            'subject_allocations' => $this->tenantQuery(SubjectPeriodAllocation::class, $subscriptionId)->count(),
            'parallel_groups' => $this->tenantQuery(ParallelGroup::class, $subscriptionId)->count(),
            'rooms' => $this->tenantQuery(Room::class, $subscriptionId)->count(),
            'rules' => $this->tenantQuery(TimetableRule::class, $subscriptionId)->count(),
            'templates' => $this->tenantQuery(TimetableTemplate::class, $subscriptionId)->count(),
            'bell_settings' => $this->tenantQuery(BellScheduleSetting::class, $subscriptionId)->count(),
            'school_bells' => $this->tenantQuery(SchoolBell::class, $subscriptionId)->count(),
            'weekly_timetables' => $this->tenantQuery(WeeklyTimetable::class, $subscriptionId)->count(),
        ];
    }

    public function readiness(int $subscriptionId): array
    {
        $checks = [
            'academic_year' => $this->activeExists(AcademicYear::class, $subscriptionId),
            'bell_schedule_setting' => $this->activeExists(BellScheduleSetting::class, $subscriptionId),
            'school_bells' => $this->activeExists(SchoolBell::class, $subscriptionId),
            'template' => $this->activeExists(TimetableTemplate::class, $subscriptionId),
            'teacher_assignment' => $this->activeExists(TeacherAssignment::class, $subscriptionId),
            'teacher_availability' => $this->teacherAvailabilityConfigured($subscriptionId),
            'teacher_workload' => $this->activeExists(TeacherWorkloadSetting::class, $subscriptionId),
            'subject_allocation' => $this->activeExists(SubjectPeriodAllocation::class, $subscriptionId),
            'rooms' => $this->activeExists(Room::class, $subscriptionId),
            'rules' => $this->activeExists(TimetableRule::class, $subscriptionId),
        ];

        $passed = collect($checks)->filter()->count();
        $total = count($checks);

        return [
            'overall_score' => $total > 0
                ? (int) round(($passed / $total) * 100)
                : 0,
            'passed_checks' => $passed,
            'total_checks' => $total,
            'is_ready' => $passed === $total,
            'checks' => $checks,
        ];
    }

    public function warnings(int $subscriptionId): array
    {
        return $this->warningsFromChecks(
            $this->readiness($subscriptionId)['checks']
        );
    }

    private function warningsFromChecks(array $checks): array
    {
        $messages = [
            'academic_year' => 'Academic Year is not configured.',
            'bell_schedule_setting' => 'Bell Schedule Setting is missing.',
            'school_bells' => 'School Bells are not generated.',
            'template' => 'Timetable Template is missing.',
            'teacher_assignment' => 'No Teacher Assignments found.',
            'teacher_availability' => 'Teacher Availability is not configured.',
            'teacher_workload' => 'Teacher Workload Settings are missing.',
            'subject_allocation' => 'Subject Period Allocation is missing.',
            'rooms' => 'Rooms are not configured.',
            'rules' => 'Timetable Rules are missing.',
        ];

        return collect($checks)
            ->reject()
            ->keys()
            ->map(fn (string $key) => $messages[$key] ?? "{$key} is not configured.")
            ->values()
            ->all();
    }

    /**
     * Teacher availability overrides are optional.
     *
     * The timetable engine treats a missing availability row as available, so
     * an empty availability table represents the valid default policy. Active
     * rows, when present, simply override that default for selected slots.
     */
    private function teacherAvailabilityConfigured(int $subscriptionId): bool
    {
        $hasInvalidOverrides = $this->tenantQuery(TeacherAvailability::class, $subscriptionId)
            ->where(function (Builder $query): void {
                $query
                    ->whereNull('teacher_id')
                    ->orWhereNull('weekday')
                    ->orWhereNull('school_bell_id')
                    ->orWhereNotIn('status', ['available', 'preferred', 'unavailable']);
            })
            ->exists();

        return ! $hasInvalidOverrides;
    }

    private function activeExists(string $modelClass, int $subscriptionId): bool
    {
        return $this->tenantQuery($modelClass, $subscriptionId)
            ->where('is_active', true)
            ->exists();
    }

    private function tenantQuery(string $modelClass, int $subscriptionId): Builder
    {
        /** @var \Illuminate\Database\Eloquent\Model $model */
        $model = new $modelClass();

        return $modelClass::query()->where(
            $model->qualifyColumn('subscription_id'),
            $subscriptionId
        );
    }
}
