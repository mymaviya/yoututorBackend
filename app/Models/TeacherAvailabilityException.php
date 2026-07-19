<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSubscription;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TeacherAvailabilityException extends Model
{
    use BelongsToSubscription;

    public const STATUS_LEAVE = 'leave';
    public const STATUS_BUSY = 'busy';
    public const STATUS_MEETING = 'meeting';
    public const STATUS_TRAINING = 'training';
    public const STATUS_EXAM_DUTY = 'exam_duty';
    public const STATUS_ASSEMBLY = 'assembly';
    public const STATUS_BLOCKED = 'blocked';
    public const STATUS_EXTRA_CLASS = 'extra_class';

    protected $fillable = [
        'subscription_id',
        'academic_year_id',
        'teacher_id',
        'exception_date',
        'weekday',
        'school_bell_id',
        'status',
        'reason',
        'remarks',
        'is_full_day',
        'is_recurring',
        'recurrence_type',
        'valid_from',
        'valid_to',
        'created_by',
        'is_active',
    ];

    protected $casts = [
        'id' => 'integer',
        'subscription_id' => 'integer',
        'academic_year_id' => 'integer',
        'teacher_id' => 'integer',
        'weekday' => 'integer',
        'school_bell_id' => 'integer',
        'created_by' => 'integer',
        'exception_date' => 'date',
        'valid_from' => 'date',
        'valid_to' => 'date',
        'is_full_day' => 'boolean',
        'is_recurring' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(
            AcademicYear::class,
            'academic_year_id'
        );
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'teacher_id'
        );
    }

    public function bell(): BelongsTo
    {
        return $this->belongsTo(
            SchoolBell::class,
            'school_bell_id'
        );
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'created_by'
        );
    }

    public function substitutions(): HasMany
    {
        return $this->hasMany(
            TeacherSubstitution::class,
            'teacher_availability_exception_id'
        );
    }

    public function activeSubstitutions(): HasMany
    {
        return $this->substitutions()
            ->where('is_active', true);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeLeave(Builder $query): Builder
    {
        return $query->where(
            'status',
            self::STATUS_LEAVE
        );
    }

    public function scopeWithStatus(
        Builder $query,
        string $status
    ): Builder {
        return $query->where('status', $status);
    }

    public function scopeForDate(
        Builder $query,
        string|\DateTimeInterface $date
    ): Builder {
        return $query->whereDate(
            'exception_date',
            $date
        );
    }

    public function scopeForTeacher(
        Builder $query,
        int $teacherId
    ): Builder {
        return $query->where(
            'teacher_id',
            $teacherId
        );
    }

    public function scopeForBell(
        Builder $query,
        int $schoolBellId
    ): Builder {
        return $query->where(function (Builder $bellQuery) use ($schoolBellId) {
            $bellQuery
                ->where('is_full_day', true)
                ->orWhere(
                    'school_bell_id',
                    $schoolBellId
                );
        });
    }

    public function scopeForSubscription(
        Builder $query,
        ?int $subscriptionId
    ): Builder {
        return $query->when(
            $subscriptionId !== null,
            fn (Builder $subscriptionQuery) => $subscriptionQuery->where(
                'subscription_id',
                $subscriptionId
            )
        );
    }

    public function scopeForAcademicYear(
        Builder $query,
        ?int $academicYearId
    ): Builder {
        return $query->when(
            $academicYearId !== null,
            fn (Builder $yearQuery) => $yearQuery->where(
                'academic_year_id',
                $academicYearId
            )
        );
    }

    public function scopeForSlot(
        Builder $query,
        int $teacherId,
        string|\DateTimeInterface $date,
        int $schoolBellId
    ): Builder {
        return $query
            ->active()
            ->forTeacher($teacherId)
            ->forDate($date)
            ->forBell($schoolBellId);
    }

    public function scopeRecurring(Builder $query): Builder
    {
        return $query->where('is_recurring', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query
            ->orderBy('exception_date')
            ->orderByDesc('is_full_day')
            ->orderBy('school_bell_id')
            ->orderBy('teacher_id')
            ->orderBy('id');
    }

    public function hasStatus(string $status): bool
    {
        return $this->status === $status;
    }

    public function isLeave(): bool
    {
        return $this->hasStatus(self::STATUS_LEAVE);
    }

    public function isExtraClass(): bool
    {
        return $this->hasStatus(
            self::STATUS_EXTRA_CLASS
        );
    }

    public function blocksRegularTeaching(): bool
    {
        return $this->is_active
            && !$this->isExtraClass();
    }

    public function isBusy(): bool
    {
        return $this->blocksRegularTeaching()
            && !$this->isLeave();
    }

    public function isFullDay(): bool
    {
        return $this->is_full_day;
    }

    public function affectsBell(?int $bellId): bool
    {
        if (!$this->is_active) {
            return false;
        }

        if ($this->isFullDay()) {
            return true;
        }

        return $bellId !== null
            && $this->school_bell_id === $bellId;
    }

    public function displayReason(): ?string
    {
        $reason = trim(
            (string) ($this->reason ?: $this->remarks)
        );

        return $reason !== '' ? $reason : null;
    }

    public static function statuses(): array
    {
        return [
            self::STATUS_BUSY,
            self::STATUS_LEAVE,
            self::STATUS_MEETING,
            self::STATUS_TRAINING,
            self::STATUS_EXAM_DUTY,
            self::STATUS_ASSEMBLY,
            self::STATUS_BLOCKED,
            self::STATUS_EXTRA_CLASS,
        ];
    }

    public static function blockingStatuses(): array
    {
        return array_values(
            array_diff(
                self::statuses(),
                [self::STATUS_EXTRA_CLASS]
            )
        );
    }
}