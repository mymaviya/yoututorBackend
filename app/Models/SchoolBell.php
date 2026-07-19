<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SchoolBell extends Model
{
    protected $fillable = [
        'subscription_id',
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
        'id' => 'integer',
        'subscription_id' => 'integer',
        'duration_minutes' => 'integer',
        'period_number' => 'integer',
        'sort_order' => 'integer',
        'is_teaching_period' => 'boolean',
        'is_break' => 'boolean',
        'is_dispersal' => 'boolean',
        'effective_from' => 'date',
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saving(function (SchoolBell $bell): void {
            if (
                filled($bell->start_time)
                && $bell->duration_minutes !== null
            ) {
                $bell->end_time = Carbon::parse($bell->start_time)
                    ->addMinutes($bell->duration_minutes)
                    ->format('H:i:s');
            }
        });
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function timetableEntries(): HasMany
    {
        return $this->hasMany(TimetableEntry::class, 'school_bell_id');
    }

    public function activeTimetableEntries(): HasMany
    {
        return $this->timetableEntries()->where('is_active', true);
    }

    public function scopeForSubscription(
        Builder $query,
        int $subscriptionId
    ): Builder {
        return $query->where('subscription_id', $subscriptionId);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeTeachingPeriods(Builder $query): Builder
    {
        return $query->where('is_teaching_period', true);
    }

    public function scopeBreaks(Builder $query): Builder
    {
        return $query->where('is_break', true);
    }

    public function scopeEffectiveOn(
        Builder $query,
        string|\DateTimeInterface|null $date = null
    ): Builder {
        return $query->where(function (Builder $dateQuery) use ($date) {
            $effectiveDate = $date ?? now();

            $dateQuery
                ->whereNull('effective_from')
                ->orWhereDate('effective_from', '<=', $effectiveDate);
        });
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query
            ->orderBy('sort_order')
            ->orderBy('start_time')
            ->orderBy('id');
    }

    public function getDisplayTimeAttribute(): string
    {
        if (blank($this->start_time) || blank($this->end_time)) {
            return '—';
        }

        return sprintf(
            '%s - %s',
            Carbon::parse($this->start_time)->format('h:i A'),
            Carbon::parse($this->end_time)->format('h:i A')
        );
    }

    public function getDisplayTitleAttribute(): string
    {
        if (filled($this->title)) {
            return $this->title;
        }

        if ($this->period_number !== null) {
            return 'Period ' . $this->period_number;
        }

        return ucfirst((string) $this->type);
    }

    public function isAvailableForTimetable(): bool
    {
        return $this->is_active
            && $this->is_teaching_period
            && ! $this->is_break
            && ! $this->is_dispersal;
    }
}
