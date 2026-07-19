<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSubscription;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TimetableTemplate extends Model
{
    use BelongsToSubscription;

    public const TYPES = ['regular', 'summer', 'winter', 'special'];

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

    protected static function booted(): void
    {
        static::saving(function (TimetableTemplate $template): void {
            if (
                $template->effective_from !== null
                && $template->effective_to !== null
                && $template->effective_to->lt($template->effective_from)
            ) {
                throw new \InvalidArgumentException('Effective To must be on or after Effective From.');
            }
        });

        static::saved(function (TimetableTemplate $template): void {
            if (! $template->is_default) {
                return;
            }

            static::query()
                ->withoutGlobalScopes()
                ->where('subscription_id', $template->subscription_id)
                ->whereKeyNot($template->getKey())
                ->where('is_default', true)
                ->update(['is_default' => false]);
        });
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function weeklyTimetables(): HasMany
    {
        return $this->hasMany(WeeklyTimetable::class, 'timetable_template_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeDefault(Builder $query): Builder
    {
        return $query->where('is_default', true);
    }

    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    public function scopeEffectiveOn(
        Builder $query,
        string|\DateTimeInterface|null $date = null
    ): Builder {
        $effectiveDate = $date ?? now();

        return $query
            ->where(function (Builder $fromQuery) use ($effectiveDate) {
                $fromQuery
                    ->whereNull('effective_from')
                    ->orWhereDate('effective_from', '<=', $effectiveDate);
            })
            ->where(function (Builder $toQuery) use ($effectiveDate) {
                $toQuery
                    ->whereNull('effective_to')
                    ->orWhereDate('effective_to', '>=', $effectiveDate);
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
            && $this->effective_from->copy()->startOfDay()->gt($effectiveDate)
        ) {
            return false;
        }

        if (
            $this->effective_to !== null
            && $this->effective_to->copy()->startOfDay()->lt($effectiveDate)
        ) {
            return false;
        }

        return $this->is_active;
    }
}
