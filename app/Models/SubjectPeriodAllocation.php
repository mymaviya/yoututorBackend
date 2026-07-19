<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSubscription;
use Illuminate\Database\Eloquent\Builder;
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
        'subscription_id' => 'integer',
        'academic_year_id' => 'integer',
        'grade_id' => 'integer',
        'section_id' => 'integer',
        'stream_id' => 'integer',
        'subject_id' => 'integer',
        'preferred_teacher_id' => 'integer',
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

    public function scopeForSubscription(Builder $query, int $subscriptionId): Builder
    {
        return $query->where(
            $query->getModel()->qualifyColumn('subscription_id'),
            $subscriptionId
        );
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where(
            $query->getModel()->qualifyColumn('is_active'),
            true
        );
    }

    public function scopeForAcademicYear(Builder $query, ?int $academicYearId): Builder
    {
        return $query->where(
            $query->getModel()->qualifyColumn('academic_year_id'),
            $academicYearId
        );
    }

    public function scopeForClass(
        Builder $query,
        int $gradeId,
        ?int $sectionId = null,
        ?int $streamId = null
    ): Builder {
        return $query
            ->where($query->getModel()->qualifyColumn('grade_id'), $gradeId)
            ->where($query->getModel()->qualifyColumn('section_id'), $sectionId)
            ->where($query->getModel()->qualifyColumn('stream_id'), $streamId);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query
            ->orderByDesc($query->getModel()->qualifyColumn('priority'))
            ->orderBy($query->getModel()->qualifyColumn('subject_category'))
            ->orderBy($query->getModel()->qualifyColumn('subject_id'));
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
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

    public function preferredTeacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'preferred_teacher_id');
    }
}
