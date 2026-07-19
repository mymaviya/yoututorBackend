<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
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
        'id' => 'integer',
        'academic_year_id' => 'integer',
        'grade_id' => 'integer',
        'section_id' => 'integer',
        'stream_id' => 'integer',
        'timetable_template_id' => 'integer',
        'effective_from' => 'date',
        'is_active' => 'boolean',
        'is_generated' => 'boolean',
    ];

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(
            AcademicYear::class,
            'academic_year_id'
        );
    }

    public function grade(): BelongsTo
    {
        return $this->belongsTo(Grade::class, 'grade_id');
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class, 'section_id');
    }

    public function stream(): BelongsTo
    {
        return $this->belongsTo(Stream::class, 'stream_id');
    }

    public function entries(): HasMany
    {
        return $this->hasMany(
            TimetableEntry::class,
            'weekly_timetable_id'
        );
    }

    public function activeEntries(): HasMany
    {
        return $this->entries()->where('is_active', true);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(
            TimetableTemplate::class,
            'timetable_template_id'
        );
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeGenerated(Builder $query): Builder
    {
        return $query->where('is_generated', true);
    }

    public function scopeEffectiveOn(
        Builder $query,
        string|\DateTimeInterface|null $date = null
    ): Builder {
        return $query->whereDate(
            'effective_from',
            '<=',
            $date ?? now()
        );
    }

    public function scopeForAcademicYear(
        Builder $query,
        int $academicYearId
    ): Builder {
        return $query->where(
            'academic_year_id',
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
            ->where('grade_id', $gradeId)
            ->when(
                $sectionId !== null,
                fn (Builder $builder) => $builder->where(
                    'section_id',
                    $sectionId
                )
            )
            ->when(
                $streamId !== null,
                fn (Builder $builder) => $builder->where(
                    'stream_id',
                    $streamId
                )
            );
    }

    public function scopeForSubscription(
        Builder $query,
        int $subscriptionId
    ): Builder {
        return $query->whereHas(
            'template',
            fn (Builder $templateQuery) => $templateQuery->where(
                'subscription_id',
                $subscriptionId
            )
        );
    }

    public function scopeCurrent(Builder $query): Builder
    {
        return $query
            ->active()
            ->generated()
            ->effectiveOn()
            ->orderByDesc('effective_from')
            ->orderByDesc('id');
    }
}