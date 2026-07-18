<?php

namespace App\Events;

use App\Models\TimetableEntry;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TeacherTimetableUpdated
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public TimetableEntry $timetableEntry
    ) {
        $this->timetableEntry->loadMissing([
            'teacher',
            'substituteTeacher',
            'subject',
            'bell',
            'weeklyTimetable.grade',
            'weeklyTimetable.section',
            'weeklyTimetable.stream',
        ]);
    }

    public function broadcastData(): array
    {
        return [
            'id' => $this->timetableEntry->id,
            'teacher_id' => $this->timetableEntry->teacher_id,
            'substitute_teacher_id' => $this->timetableEntry->substitute_teacher_id,
            'subject_id' => $this->timetableEntry->subject_id,
            'school_bell_id' => $this->timetableEntry->school_bell_id,
            'weekday' => $this->timetableEntry->weekday,
            'room_no' => $this->timetableEntry->room_no,
            'is_substitution' => (bool) $this->timetableEntry->is_substitution,
            'is_active' => (bool) $this->timetableEntry->is_active,
            'teacher' => $this->timetableEntry->teacher,
            'substitute_teacher' => $this->timetableEntry->substituteTeacher,
            'subject' => $this->timetableEntry->subject,
            'bell' => $this->timetableEntry->bell,
            'grade' => $this->timetableEntry->weeklyTimetable?->grade,
            'section' => $this->timetableEntry->weeklyTimetable?->section,
            'stream' => $this->timetableEntry->weeklyTimetable?->stream,
        ];
    }
}