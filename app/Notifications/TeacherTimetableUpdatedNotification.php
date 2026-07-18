<?php

namespace App\Notifications;

use App\Models\TimetableEntry;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\DatabaseMessage;
use Illuminate\Notifications\Notification;

class TeacherTimetableUpdatedNotification extends Notification
{
    use Queueable;

    public function __construct(
        protected TimetableEntry $entry
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): DatabaseMessage
    {
        return new DatabaseMessage([
            'title' => 'Teacher Timetable Updated',

            'message' => sprintf(
                '%s timetable has been updated for %s (%s).',
                optional($this->entry->teacher)->name,
                $this->entry->weekday,
                optional($this->entry->bell)->title
            ),

            'type' => 'teacher_timetable',

            'timetable_entry_id' => $this->entry->id,

            'teacher_id' => $this->entry->teacher_id,

            'substitute_teacher_id' => $this->entry->substitute_teacher_id,

            'weekday' => $this->entry->weekday,

            'school_bell_id' => $this->entry->school_bell_id,

            'subject_id' => $this->entry->subject_id,

            'is_substitution' => (bool) $this->entry->is_substitution,
        ]);
    }
}