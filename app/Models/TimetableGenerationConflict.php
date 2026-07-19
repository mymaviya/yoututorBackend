<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TimetableGenerationConflict extends Model
{
    protected $fillable = [
        'timetable_generation_run_id',
        'item_index',
        'conflict_type',
        'severity',
        'grade_id',
        'section_id',
        'stream_id',
        'teacher_id',
        'subject_id',
        'school_bell_id',
        'weekday',
        'message',
        'context',
    ];

    protected $casts = [
        'timetable_generation_run_id' => 'integer',
        'item_index' => 'integer',
        'grade_id' => 'integer',
        'section_id' => 'integer',
        'stream_id' => 'integer',
        'teacher_id' => 'integer',
        'subject_id' => 'integer',
        'school_bell_id' => 'integer',
        'weekday' => 'integer',
        'context' => 'array',
    ];

    public function run(): BelongsTo
    {
        return $this->belongsTo(
            TimetableGenerationRun::class,
            'timetable_generation_run_id'
        );
    }
}
