<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ParallelGroup extends Model
{
    protected $fillable = [
        'grade_id',
        'name',
        'same_period_required',
        'period_number_fixed',
        'preferred_period_number',
        'weekly_periods',
        'prefer_morning',
        'prefer_last_period',
        'prefer_saturday',
        'is_active',
    ];

    protected $casts = [
        'id' => 'integer',
        'grade_id' => 'integer',
        'same_period_required' => 'boolean',
        'period_number_fixed' => 'boolean',
        'preferred_period_number' => 'integer',
        'weekly_periods' => 'integer',
        'prefer_morning' => 'boolean',
        'prefer_last_period' => 'boolean',
        'prefer_saturday' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function grade(): BelongsTo
    {
        return $this->belongsTo(Grade::class, 'grade_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(
            ParallelGroupItem::class,
            'parallel_group_id'
        );
    }

    public function activeItems(): HasMany
    {
        return $this->items()->where('is_active', true);
    }

    public function timetableEntries(): HasMany
    {
        return $this->hasMany(
            TimetableEntry::class,
            'parallel_group_id'
        );
    }

    public function activeTimetableEntries(): HasMany
    {
        return $this->timetableEntries()
            ->where('is_active', true);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeForGrade(
        Builder $query,
        int $gradeId
    ): Builder {
        return $query->where('grade_id', $gradeId);
    }

    public function scopeFixedPeriod(Builder $query): Builder
    {
        return $query->where('period_number_fixed', true);
    }

    public function scopeSamePeriodRequired(
        Builder $query
    ): Builder {
        return $query->where('same_period_required', true);
    }

    public function hasPreferredPeriod(): bool
    {
        return $this->preferred_period_number !== null;
    }

    public function requiresFixedPeriod(): bool
    {
        return $this->period_number_fixed
            && $this->hasPreferredPeriod();
    }

    public function preferenceFlags(): array
    {
        return [
            'morning' => $this->prefer_morning,
            'last_period' => $this->prefer_last_period,
            'saturday' => $this->prefer_saturday,
        ];
    }
}