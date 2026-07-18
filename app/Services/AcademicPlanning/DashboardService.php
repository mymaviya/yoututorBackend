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

class DashboardService
{
    public function dashboard(): array
    {
        return [
            'statistics' => $this->statistics(),
            'readiness' => $this->readiness(),
            'warnings' => $this->warnings(),
        ];
    }

    public function statistics(): array
    {
        return [
            'academic_years' => AcademicYear::count(),
            'teachers' => User::teachers()->count(),
            'teacher_assignments' => TeacherAssignment::count(),
            'teacher_availability' => TeacherAvailability::count(),
            'teacher_workload' => TeacherWorkloadSetting::count(),
            'mother_teachers' => MotherTeacherSetting::count(),
            'teacher_substitutions' => TeacherSubstitution::count(),
            'subject_allocations' => SubjectPeriodAllocation::count(),
            'parallel_groups' => ParallelGroup::count(),
            'rooms' => Room::count(),
            'rules' => TimetableRule::count(),
            'templates' => TimetableTemplate::count(),
            'bell_settings' => BellScheduleSetting::count(),
            'school_bells' => SchoolBell::count(),
            'weekly_timetables' => WeeklyTimetable::count(),
        ];
    }

    public function readiness(): array
    {
        $checks = [
            'academic_year' => AcademicYear::where('is_active', true)->exists(),
            'bell_schedule_setting' => BellScheduleSetting::where('is_active', true)->exists(),
            'school_bells' => SchoolBell::where('is_active', true)->exists(),
            'template' => TimetableTemplate::where('is_active', true)->exists(),
            'teacher_assignment' => TeacherAssignment::where('is_active', true)->exists(),
            'teacher_availability' => TeacherAvailability::where('is_active', true)->exists(),
            'teacher_workload' => TeacherWorkloadSetting::where('is_active', true)->exists(),
            'subject_allocation' => SubjectPeriodAllocation::where('is_active', true)->exists(),
            'rooms' => Room::where('is_active', true)->exists(),
            'rules' => TimetableRule::where('is_active', true)->exists(),
        ];

        $passed = collect($checks)->filter()->count();
        $total = count($checks);

        return [
            'overall_score' => $total ? round(($passed / $total) * 100) : 0,
            'checks' => $checks,
        ];
    }

    public function warnings(): array
    {
        $warnings = [];

        if (! AcademicYear::where('is_active', true)->exists()) {
            $warnings[] = 'Academic Year is not configured.';
        }

        if (! BellScheduleSetting::where('is_active', true)->exists()) {
            $warnings[] = 'Bell Schedule Setting is missing.';
        }

        if (! SchoolBell::where('is_active', true)->exists()) {
            $warnings[] = 'School Bells are not generated.';
        }

        if (! TimetableTemplate::where('is_active', true)->exists()) {
            $warnings[] = 'Timetable Template is missing.';
        }

        if (! TeacherAssignment::where('is_active', true)->exists()) {
            $warnings[] = 'No Teacher Assignments found.';
        }

        if (! TeacherAvailability::where('is_active', true)->exists()) {
            $warnings[] = 'Teacher Availability is not configured.';
        }

        if (! TeacherWorkloadSetting::where('is_active', true)->exists()) {
            $warnings[] = 'Teacher Workload Settings are missing.';
        }

        if (! SubjectPeriodAllocation::where('is_active', true)->exists()) {
            $warnings[] = 'Subject Period Allocation is missing.';
        }

        if (! Room::where('is_active', true)->exists()) {
            $warnings[] = 'Rooms are not configured.';
        }

        if (! TimetableRule::where('is_active', true)->exists()) {
            $warnings[] = 'Timetable Rules are missing.';
        }

        return $warnings;
    }
}