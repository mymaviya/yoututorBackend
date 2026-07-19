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

    public const STATUS_DRAFT = 'draft';
    public const STATUS_PUBLISHED = 'published';
    public const STATUS_ARCHIVED = 'archived';
    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_PUBLISHED,
        self::STATUS_ARCHIVED,
    ];

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
        'status',
        'version',
        'published_at',
        'published_by',
        'archived_at',
    ];

    protected $casts = [
        'id' => 'integer',
        'subscription_id' => 'integer',
        'academic_year_id' => 'integer',
        'grade_id' => 'integer',
        'section_id' => 'integer',
        'stream_id' => 'integer',
        'timetable_template_id' => 'integer',
        'version' => 'integer',
        'published_by' => 'integer',
        'effective_from' => 'date',
        'published_at' => 'datetime',
        'archived_at' => 'datetime',
        'is_active' => 'boolean',
        'is_generated' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (WeeklyTimetable $timetable): void {
            $timetable->status ??= self::STATUS_DRAFT;
            $timetable->version ??= 1;
        });

        static::saving(function (WeeklyTimetable $timetable): void {
            if (! in_array($timetable->status ?? self::STATUS_DRAFT, self::STATUSES, true)) {
                throw ValidationException::withMessages([
                    'status' => 'The timetable lifecycle status is invalid.',
                ]);
            }

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

    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeGenerated(Builder $query): Builder
    {
        return $query->where('is_generated', true);
    }

    public function scopeDraft(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_DRAFT);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PUBLISHED);
    }

    public function scopeArchived(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ARCHIVED);
    }

    public function scopeEffectiveOn(
        Builder $query,
        string|\DateTimeInterface|null $date = null
    ): Builder {
        return $query->whereDate('effective_from', '<=', $date ?? now());
    }

    public function scopeForAcademicYear(Builder $query, int $academicYearId): Builder
    {
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

    public function scopeForSubscription(Builder $query, int $subscriptionId): Builder
    {
        return $query->where('subscription_id', $subscriptionId);
    }

    public function scopeCurrent(Builder $query): Builder
    {
        return $query
            ->active()
            ->generated()
            ->published()
            ->effectiveOn()
            ->orderByDesc('effective_from')
            ->orderByDesc('version')
            ->orderByDesc('id');
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isPublished(): bool
    {
        return $this->status === self::STATUS_PUBLISHED;
    }

    public function isArchived(): bool
    {
        return $this->status === self::STATUS_ARCHIVED;
    }
}
