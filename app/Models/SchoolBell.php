<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class SchoolBell extends Model
{
    protected $fillable = [
        'title',
        'type',
        'start_time',
        'duration_minutes',
        'end_time',
        'period_number',
        'is_teaching_period',
        'is_break',
        'is_dispersal',
        'effective_from',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'duration_minutes' => 'integer',
        'period_number' => 'integer',
        'is_teaching_period' => 'boolean',
        'is_break' => 'boolean',
        'is_dispersal' => 'boolean',
        'effective_from' => 'date',
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saving(function (SchoolBell $bell) {
            if ($bell->start_time && $bell->duration_minutes) {
                $bell->end_time = Carbon::parse($bell->start_time)
                    ->addMinutes($bell->duration_minutes)
                    ->format('H:i:s');
            }
        });
    }

    public function getDisplayTimeAttribute(): string
    {
        return Carbon::parse($this->start_time)->format('h:i A') .
            ' - ' .
            Carbon::parse($this->end_time)->format('h:i A');
    }
}