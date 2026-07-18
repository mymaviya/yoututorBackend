<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WeeklyTimetable extends Model
{
    protected $fillable = [
        'academic_year_id',
        'name',
        'grade_id',
        'section_id',
        'stream_id',
        'timetable_template_id',
        'effective_from',
        'is_active',
        'is_generated',
    ];

    protected $casts = [
        'effective_from' => 'date',
        'is_active' => 'boolean',
        'is_generated' => 'boolean',
    ];

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

    public function entries(): HasMany
    {
        return $this->hasMany(TimetableEntry::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(
            TimetableTemplate::class,
            'timetable_template_id'
        );
    }
}
