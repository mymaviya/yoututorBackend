<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSubscription;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubjectPeriodAllocation extends Model
{
    use BelongsToSubscription;

    protected $fillable = [
        'subscription_id',
        'academic_year_id',
        'grade_id',
        'section_id',
        'stream_id',
        'subject_id',
        'preferred_teacher_id',
        'subject_category',
        'weekly_periods',
        'max_periods_per_day',
        'prefer_double_period',
        'prefer_morning',
        'prefer_last_period',
        'prefer_saturday',
        'is_optional',
        'is_parallel_subject',
        'parallel_group_code',
        'priority',
        'is_active',
    ];

    protected $casts = [
        'weekly_periods' => 'integer',
        'max_periods_per_day' => 'integer',
        'prefer_double_period' => 'boolean',
        'prefer_morning' => 'boolean',
        'prefer_last_period' => 'boolean',
        'prefer_saturday' => 'boolean',
        'is_optional' => 'boolean',
        'is_parallel_subject' => 'boolean',
        'priority' => 'integer',
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

    public function stream(): BelongsTo
    {
        return $this->belongsTo(Stream::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function preferredTeacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'preferred_teacher_id');
    }
}