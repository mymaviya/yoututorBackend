<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BellScheduleSetting extends Model
{
    protected $fillable = [
        'name',
        'assembly_bell_time',
        'school_over_time',
        'total_periods',
        'teacher_arrival_before_assembly',
        'student_arrival_before_assembly',
        'assembly_duration',
        'break_mode',
        'short_break_after_period',
        'short_break_duration',
        'long_break_after_period',
        'long_break_duration',
        'bus_dispersal_duration',
        'teacher_dispersal_after_school_over',
        'auto_calculate_period_duration',
        'first_period_duration',
        'regular_period_duration',
        'effective_from',
        'first_period_extra_minutes',
        'period_after_break_gap',
        'bus_dispersal_enabled',
        'is_active',
    ];

    protected $casts = [
        'total_periods' => 'integer',
        'teacher_arrival_before_assembly' => 'integer',
        'student_arrival_before_assembly' => 'integer',
        'assembly_duration' => 'integer',
        'short_break_after_period' => 'integer',
        'short_break_duration' => 'integer',
        'long_break_after_period' => 'integer',
        'long_break_duration' => 'integer',
        'bus_dispersal_duration' => 'integer',
        'teacher_dispersal_after_school_over' => 'integer',
        'auto_calculate_period_duration' => 'boolean',
        'first_period_duration' => 'integer',
        'regular_period_duration' => 'integer',
        'effective_from' => 'date',
        'first_period_extra_minutes' => 'integer',
        'period_after_break_gap' => 'integer',
        'bus_dispersal_enabled' => 'boolean',
        'is_active' => 'boolean',
    ];
}
