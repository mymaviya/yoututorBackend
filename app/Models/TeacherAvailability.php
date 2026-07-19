<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSubscription;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeacherAvailability extends Model
{
    use BelongsToSubscription;

    protected $fillable = [
        'subscription_id',
        'teacher_id',
        'weekday',
        'school_bell_id',
        'status',
        'reason',
        'is_active',
    ];

    protected $casts = [
        'id' => 'integer',
        'subscription_id' => 'integer',
        'teacher_id' => 'integer',
        'weekday' => 'integer',
        'school_bell_id' => 'integer',
        'is_active' => 'boolean',
    ];

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

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeForTeacher(
        Builder $query,
        int $teacherId
    ): Builder {
        return $query->where('teacher_id', $teacherId);
    }

    public function scopeForWeekday(
        Builder $query,
        int $weekday
    ): Builder {
        return $query->where('weekday', $weekday);
    }

    public function scopeForBell(
        Builder $query,
        int $schoolBellId
    ): Builder {
        return $query->where(
            'school_bell_id',
            $schoolBellId
        );
    }

    public function scopeWithStatus(
        Builder $query,
        string $status
    ): Builder {
        return $query->where('status', $status);
    }

    public function scopeForSlot(
        Builder $query,
        int $teacherId,
        int $weekday,
        int $schoolBellId
    ): Builder {
        return $query
            ->forTeacher($teacherId)
            ->forWeekday($weekday)
            ->forBell($schoolBellId);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query
            ->orderBy('weekday')
            ->orderBy('school_bell_id')
            ->orderBy('teacher_id')
            ->orderBy('id');
    }

    public function hasStatus(string $status): bool
    {
        return strcasecmp(
            (string) $this->status,
            $status
        ) === 0;
    }

    public function isAvailable(): bool
    {
        return $this->is_active
            && $this->hasStatus('available');
    }

    public function isUnavailable(): bool
    {
        return $this->is_active
            && $this->hasStatus('unavailable');
    }

    public function isPreferred(): bool
    {
        return $this->is_active
            && $this->hasStatus('preferred');
    }

    public function displayReason(): ?string
    {
        $reason = trim((string) $this->reason);

        return $reason !== '' ? $reason : null;
    }
}