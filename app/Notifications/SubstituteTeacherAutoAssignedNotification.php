<?php

namespace App\Notifications;

use App\Models\TeacherSubstitution;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class SubstituteTeacherAutoAssignedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public TeacherSubstitution $substitution
    ) {
        $this->substitution->loadMissing([
            'originalTeacher',
            'absentTeacher',
            'substituteTeacher',
            'timetableEntry.bell',
            'grade',
            'section',
            'subject',
        ]);
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $bell = $this->substitution->timetableEntry?->bell;

        return [
            'title' => 'Substitute Teacher Auto Assigned',
            'message' => $this->message(),
            'type' => 'teacher_substitution_auto_assigned',

            'substitution_id' => $this->substitution->id,
            'subscription_id' => $this->substitution->subscription_id,
            'academic_year_id' => $this->substitution->academic_year_id,

            'original_teacher_id' => $this->substitution->original_teacher_id,
            'absent_teacher_id' => $this->substitution->original_teacher_id,
            'substitute_teacher_id' => $this->substitution->substitute_teacher_id,

            'original_teacher_name' => $this->substitution->absentTeacher?->name,
            'substitute_teacher_name' => $this->substitution->substituteTeacher?->name,

            'timetable_entry_id' => $this->substitution->timetable_entry_id,
            'school_bell_id' => $bell?->id,
            'period_number' => $bell?->period_number,
            'period_title' => $bell?->title,

            'grade_id' => $this->substitution->grade_id,
            'grade_name' => $this->substitution->grade?->name,
            'section_id' => $this->substitution->section_id,
            'section_name' => $this->substitution->section?->name,
            'subject_id' => $this->substitution->subject_id,
            'subject_name' => $this->substitution->subject?->name,

            'date' => $this->substitution->substitution_date?->toDateString(),
            'status' => $this->substitution->status,
            'ai_score' => $this->substitution->ai_score,
            'ai_reason' => $this->substitution->ai_reason,
        ];
    }

    private function message(): string
    {
        $absent = $this->substitution->absentTeacher?->name
            ?? $this->substitution->originalTeacher?->name
            ?? 'Teacher';

        $substitute = $this->substitution->substituteTeacher?->name
            ?? 'Substitute teacher';

        $bell = $this->substitution->timetableEntry?->bell;
        $period = $bell
            ? " for Period {$bell->period_number}"
            : '';

        return "{$substitute} was auto-assigned for {$absent}{$period}.";
    }
}
