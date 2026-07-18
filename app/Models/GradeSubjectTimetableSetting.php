<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GradeSubjectTimetableSetting extends Model
{
    protected $fillable = [
        'grade_id',
        'subject_id',
        'teacher_id',
        'category',
        'weekly_periods',
        'max_periods_per_day',
        'prefer_double_period',
        'prefer_morning',
        'prefer_last_period',
        'prefer_saturday',
        'is_parallel_subject',
        'parallel_group_code',
        'is_active',
    ];

    protected $casts = [
        'weekly_periods' => 'integer',
        'max_periods_per_day' => 'integer',
        'prefer_double_period' => 'boolean',
        'prefer_morning' => 'boolean',
        'prefer_last_period' => 'boolean',
        'prefer_saturday' => 'boolean',
        'is_parallel_subject' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function grade(): BelongsTo
    {
        return $this->belongsTo(Grade::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }
}