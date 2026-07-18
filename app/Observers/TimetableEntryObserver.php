<?php

namespace App\Observers;

use App\Models\TeacherTimetable;
use App\Models\TimetableEntry;

class TimetableEntryObserver
{
    /**
     * Create/Update Teacher Timetable record.
     */
    public function saved(TimetableEntry $entry): void
    {
        if (! $entry->weeklyTimetable) {
            return;
        }

        TeacherTimetable::updateOrCreate(
            [
                'timetable_entry_id' => $entry->id,
            ],
            [
                'teacher_id'     => $entry->teacher_id,
                'grade_id'       => $entry->weeklyTimetable->grade_id,
                'section_id'     => $entry->weeklyTimetable->section_id,
                'stream_id'      => $entry->weeklyTimetable->stream_id,
                'subject_id'     => $entry->subject_id,
                'school_bell_id' => $entry->school_bell_id,
                'weekday'        => $entry->weekday,
                'room_no'        => $entry->room_no,
                'is_active'      => (bool) $entry->is_active,
            ]
        );
    }

    /**
     * Delete Teacher Timetable record.
     */
    public function deleted(TimetableEntry $entry): void
    {
        TeacherTimetable::where(
            'timetable_entry_id',
            $entry->id
        )->delete();
    }

    /**
     * Restore Teacher Timetable record.
     */
    public function restored(TimetableEntry $entry): void
    {
        $this->saved($entry);
    }

    /**
     * Force delete Teacher Timetable record.
     */
    public function forceDeleted(TimetableEntry $entry): void
    {
        TeacherTimetable::where(
            'timetable_entry_id',
            $entry->id
        )->forceDelete();
    }
}