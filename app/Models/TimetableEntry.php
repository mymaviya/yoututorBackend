<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TimetableEntry extends Model
{
    protected $fillable = [
        'weekly_timetable_id',
        'weekday',
        'school_bell_id',
        'teacher_id',
        'subject_id',
        'lesson_id',
        'parallel_group_id',
        'student_group_name',
        'room_no',
        'is_parallel',
        'is_substitution',
        'substitute_teacher_id',
        'remarks',
        'is_active',
    ];

    protected $casts = [
        'is_parallel' => 'boolean',
        'is_substitution' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function weeklyTimetable(): BelongsTo
    {
        return $this->belongsTo(WeeklyTimetable::class);
    }

    public function bell(): BelongsTo
    {
        return $this->belongsTo(SchoolBell::class, 'school_bell_id');
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    public function parallelGroup(): BelongsTo
    {
        return $this->belongsTo(ParallelGroup::class, 'parallel_group_id');
    }

    public function substituteTeacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'substitute_teacher_id');
    }
}
