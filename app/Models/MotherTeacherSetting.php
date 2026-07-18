<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSubscription;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MotherTeacherSetting extends Model
{
    use BelongsToSubscription;

    protected $fillable = [
        'subscription_id',
        'grade_id',
        'section_id',
        'mother_teacher_id',
        'max_subjects_per_week',
        'max_periods_per_day',
        'max_periods_per_week',
        'excluded_subject_ids',
        'force_first_period',
        'prefer_first_half',
        'is_active',
    ];

    protected $casts = [
        'max_subjects_per_week' => 'integer',
        'max_periods_per_day' => 'integer',
        'max_periods_per_week' => 'integer',
        'excluded_subject_ids' => 'array',
        'force_first_period' => 'boolean',
        'prefer_first_half' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function grade(): BelongsTo
    {
        return $this->belongsTo(Grade::class);
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    public function motherTeacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'mother_teacher_id');
    }
}