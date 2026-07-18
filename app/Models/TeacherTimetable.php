<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeacherTimetable extends Model
{
    protected $table = 'teacher_timetable_views';

    protected $fillable = [
        'timetable_entry_id',
        'teacher_id',
        'grade_id',
        'section_id',
        'stream_id',
        'subject_id',
        'school_bell_id',
        'weekday',
        'room_no',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function timetableEntry(): BelongsTo
    {
        return $this->belongsTo(TimetableEntry::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function grade(): BelongsTo
    {
        return $this->belongsTo(Grade::class);
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    public function stream(): BelongsTo
    {
        return $this->belongsTo(Stream::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function bell(): BelongsTo
    {
        return $this->belongsTo(SchoolBell::class, 'school_bell_id');
    }
}
