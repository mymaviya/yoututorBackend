<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSubscription;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

class WeeklyTimetable extends Model
{
    use BelongsToSubscription;

    protected $fillable = [
        'subscription_id',
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
        'subscription_id' => 'integer',
        'academic_year_id' => 'integer',
        'grade_id' => 'integer',
        'section_id' => 'integer',
        'stream_id' => 'integer',
        'timetable_template_id' => 'integer',
        'effective_from' => 'date',
        'is_active' => 'boolean',
        'is_generated' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saving(function (WeeklyTimetable $timetable): void {
            if (! $timetable->timetable_template_id) {
                return;
            }

            $templateSubscriptionId = TimetableTemplate::query()
                ->withoutGlobalScopes()
                ->whereKey($timetable->timetable_template_id)
                ->value('subscription_id');

            if ($templateSubscriptionId === null) {
                throw ValidationException::withMessages([
                    'timetable_template_id' => 'The selected timetable template does not exist.',
                ]);
            }

            if (
                $timetable->subscription_id !== null
                && (int) $timetable->subscription_id !== (int) $templateSubscriptionId
            ) {
                throw ValidationException::withMessages([
                    'timetable_template_id' => 'The selected timetable template belongs to another subscription.',
                ]);
            }

            $timetable->subscription_id = (int) $templateSubscriptionId;
        });
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class, 'academic_year_id');
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
        return $this->hasMany(TimetableEntry::class, 'weekly_timetable_id');
    }

    public function activeEntries(): HasMany
    {
        return $this->entries()->where('is_active', true);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(TimetableTemplate::class, 'timetable_template_id');
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
        return $query->whereDate('effective_from', '<=', $date ?? now());
    }

    public function scopeForAcademicYear(
        Builder $query,
        int $academicYearId
    ): Builder {
        return $query->where('academic_year_id', $academicYearId);
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
                fn (Builder $builder) => $builder->where('section_id', $sectionId)
            )
            ->when(
                $streamId !== null,
                fn (Builder $builder) => $builder->where('stream_id', $streamId)
            );
    }

    public function scopeForSubscription(
        Builder $query,
        int $subscriptionId
    ): Builder {
        return $query->where('subscription_id', $subscriptionId);
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
