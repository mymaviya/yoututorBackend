<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TimetableGenerationSetting extends Model
{
    protected $fillable = [
        'name',
        'major_subject_daily_required',
        'minor_subject_saturday_preference',
        'class_teacher_first_period',
        'double_period_min_weekly_periods',
        'max_consecutive_periods',
        'max_same_subject_per_day',
        'prefer_minor_last_period',
        'prefer_math_morning',
        'avoid_major_last_period',
        'allow_parallel_subjects',
        'allow_stream_parallel_groups',
        'teacher_clash_check',
        'room_clash_check',
        'is_active',
    ];

    protected $casts = [
        'major_subject_daily_required' => 'boolean',
        'minor_subject_saturday_preference' => 'boolean',
        'class_teacher_first_period' => 'boolean',
        'double_period_min_weekly_periods' => 'integer',
        'max_consecutive_periods' => 'integer',
        'max_same_subject_per_day' => 'integer',
        'prefer_minor_last_period' => 'boolean',
        'prefer_math_morning' => 'boolean',
        'avoid_major_last_period' => 'boolean',
        'allow_parallel_subjects' => 'boolean',
        'allow_stream_parallel_groups' => 'boolean',
        'teacher_clash_check' => 'boolean',
        'room_clash_check' => 'boolean',
        'is_active' => 'boolean',
    ];
}