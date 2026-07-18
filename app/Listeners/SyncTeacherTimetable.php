<?php

namespace App\Listeners;

use App\Events\TeacherTimetableUpdated;
use App\Models\TeacherTimetable;

class SyncTeacherTimetable
{
    /**
     * Handle the event.
     */
    public function handle(
        TeacherTimetableUpdated $event
    ): void {
        $entry = $event->timetableEntry;

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
}