<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSubscription;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeacherWorkloadSetting extends Model
{
    use BelongsToSubscription;

    protected $fillable = [
        'subscription_id',
        'teacher_id',
        'max_periods_per_day',
        'max_periods_per_week',
        'max_consecutive_periods',
        'min_free_periods_per_day',
        'allow_first_period',
        'allow_last_period',
        'is_class_teacher',
        'is_active',
    ];

    protected $casts = [
        'max_periods_per_day' => 'integer',
        'max_periods_per_week' => 'integer',
        'max_consecutive_periods' => 'integer',
        'min_free_periods_per_day' => 'integer',
        'allow_first_period' => 'boolean',
        'allow_last_period' => 'boolean',
        'is_class_teacher' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }
}