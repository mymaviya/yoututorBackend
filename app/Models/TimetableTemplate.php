<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSubscription;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TimetableTemplate extends Model
{
    use BelongsToSubscription;

    protected $fillable = [
        'subscription_id',
        'name',
        'type',
        'effective_from',
        'effective_to',
        'is_default',
        'is_active',
    ];

    protected $casts = [
        'id' => 'integer',
        'subscription_id' => 'integer',
        'effective_from' => 'date',
        'effective_to' => 'date',
        'is_default' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function weeklyTimetables(): HasMany
    {
        return $this->hasMany(
            WeeklyTimetable::class,
            'timetable_template_id'
        );
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeDefault(Builder $query): Builder
    {
        return $query->where('is_default', true);
    }

    public function scopeOfType(
        Builder $query,
        string $type
    ): Builder {
        return $query->where('type', $type);
    }

    public function scopeEffectiveOn(
        Builder $query,
        string|\DateTimeInterface|null $date = null
    ): Builder {
        $effectiveDate = $date ?? now();

        return $query
            ->whereDate(
                'effective_from',
                '<=',
                $effectiveDate
            )
            ->where(function (Builder $dateQuery) use (
                $effectiveDate
            ) {
                $dateQuery
                    ->whereNull('effective_to')
                    ->orWhereDate(
                        'effective_to',
                        '>=',
                        $effectiveDate
                    );
            });
    }

    public function scopeCurrent(Builder $query): Builder
    {
        return $query
            ->active()
            ->effectiveOn()
            ->orderByDesc('is_default')
            ->orderByDesc('effective_from')
            ->orderByDesc('id');
    }

    public function isEffectiveOn(
        string|\DateTimeInterface|null $date = null
    ): bool {
        $effectiveDate = Carbon::parse($date ?? now())->startOfDay();

        if (
            $this->effective_from !== null
            && $this->effective_from->startOfDay()->gt($effectiveDate)
        ) {
            return false;
        }

        if (
            $this->effective_to !== null
            && $this->effective_to->startOfDay()->lt($effectiveDate)
        ) {
            return false;
        }

        return $this->is_active;
    }
}