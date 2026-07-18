<?php

namespace App\Mail;

use App\Models\TimetableEntry;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class TeacherTimetableUpdatedMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public TimetableEntry $entry
    ) {
        $this->entry->loadMissing([
            'teacher',
            'substituteTeacher',
            'subject',
            'bell',
            'weeklyTimetable.grade',
            'weeklyTimetable.section',
            'weeklyTimetable.stream',
        ]);
    }

    public function build(): self
    {
        return $this
            ->subject('Teacher Timetable Updated')
            ->view('emails.teacher-timetable-updated')
            ->with([
                'entry' => $this->entry,
                'teacher' => $this->entry->teacher,
                'substituteTeacher' => $this->entry->substituteTeacher,
                'subject' => $this->entry->subject,
                'bell' => $this->entry->bell,
                'grade' => $this->entry->weeklyTimetable?->grade,
                'section' => $this->entry->weeklyTimetable?->section,
                'stream' => $this->entry->weeklyTimetable?->stream,
            ]);
    }
}