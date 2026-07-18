<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSubscription;
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
        'exception_date' => 'date',
        'valid_from' => 'date',
        'valid_to' => 'date',
        'is_full_day' => 'boolean',
        'is_recurring' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function bell(): BelongsTo
    {
        return $this->belongsTo(SchoolBell::class, 'school_bell_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function substitutions(): HasMany
    {
        return $this->hasMany(TeacherSubstitution::class, 'teacher_availability_exception_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeLeave($query)
    {
        return $query->where('status', self::STATUS_LEAVE);
    }

    public function scopeForDate($query, $date)
    {
        return $query->whereDate('exception_date', $date);
    }

    public function scopeForTeacher($query, int $teacherId)
    {
        return $query->where('teacher_id', $teacherId);
    }

    public function scopeForSubscription($query, ?int $subscriptionId)
    {
        return $query->when($subscriptionId, fn ($q) => $q->where('subscription_id', $subscriptionId));
    }

    public function scopeForAcademicYear($query, ?int $academicYearId)
    {
        return $query->when($academicYearId, fn ($q) => $q->where('academic_year_id', $academicYearId));
    }

    public function isLeave(): bool
    {
        return $this->status === self::STATUS_LEAVE;
    }

    public function isBusy(): bool
    {
        return ! $this->isLeave();
    }

    public function isFullDay(): bool
    {
        return (bool) $this->is_full_day;
    }

    public function affectsBell(?int $bellId): bool
    {
        if ($this->isFullDay()) {
            return true;
        }

        return (int) $this->school_bell_id === (int) $bellId;
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
}
