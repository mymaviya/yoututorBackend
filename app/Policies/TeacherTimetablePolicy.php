<?php

namespace App\Policies;

use App\Models\TeacherTimetable;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class TeacherTimetablePolicy
{
    /**
     * View timetable list
     */
    public function viewAny(User $user): bool
    {
        return $user->can('teacher.timetable');
    }

    /**
     * View teacher timetable
     */
    public function view(User $user): bool
    {
        return $user->can('teacher.timetable');
    }

    /**
     * Print timetable
     */
    public function print(User $user): bool
    {
        return $user->can('teacher.timetable.print')
            || $user->can('teacher.timetable');
    }

    /**
     * Export timetable
     */
    public function export(User $user): bool
    {
        return $user->can('teacher.timetable.export')
            || $user->can('teacher.timetable');
    }

    /**
     * View workload
     */
    public function workload(User $user): bool
    {
        return $user->can('teacher.timetable.workload')
            || $user->can('teacher.timetable');
    }

    /**
     * View free periods
     */
    public function freePeriods(User $user): bool
    {
        return $user->can('teacher.timetable.free_periods')
            || $user->can('teacher.timetable');
    }

    /**
     * Generate timetable (Future)
     */
    public function generate(User $user): bool
    {
        return $user->can('teacher.timetable.generate');
    }

    /**
     * Publish timetable (Future)
     */
    public function publish(User $user): bool
    {
        return $user->can('teacher.timetable.publish');
    }

    /**
     * Delete timetable (Future)
     */
    public function delete(User $user): bool
    {
        return $user->can('teacher.timetable.delete');
    }
}