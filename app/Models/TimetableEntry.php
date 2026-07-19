<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TimetableEntry extends Model
{
    protected $fillable = [
        'weekly_timetable_id',
        'weekday',
        'school_bell_id',
        'teacher_id',
        'subject_id',
        'lesson_id',
        'parallel_group_id',
        'student_group_name',
        'room_id',
        'room_no',
        'is_parallel',
        'is_substitution',
        'substitute_teacher_id',
        'remarks',
        'is_locked',
        'is_active',
    ];

    protected $casts = [
        'id' => 'integer',
        'weekly_timetable_id' => 'integer',
        'weekday' => 'integer',
        'school_bell_id' => 'integer',
        'teacher_id' => 'integer',
        'subject_id' => 'integer',
        'lesson_id' => 'integer',
        'parallel_group_id' => 'integer',
        'room_id' => 'integer',
        'substitute_teacher_id' => 'integer',
        'is_parallel' => 'boolean',
        'is_substitution' => 'boolean',
        'is_locked' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function weeklyTimetable(): BelongsTo
    {
        return $this->belongsTo(WeeklyTimetable::class, 'weekly_timetable_id');
    }

    public function bell(): BelongsTo
    {
        return $this->belongsTo(SchoolBell::class, 'school_bell_id');
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class, 'subject_id');
    }

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class, 'lesson_id');
    }

    public function parallelGroup(): BelongsTo
    {
        return $this->belongsTo(ParallelGroup::class, 'parallel_group_id');
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(TimetableRoom::class, 'room_id');
    }

    public function substituteTeacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'substitute_teacher_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where($query->getModel()->qualifyColumn('is_active'), true);
    }

    public function scopeLocked(Builder $query): Builder
    {
        return $query->where($query->getModel()->qualifyColumn('is_locked'), true);
    }

    public function scopeUnlocked(Builder $query): Builder
    {
        return $query->where($query->getModel()->qualifyColumn('is_locked'), false);
    }

    public function scopeForWeekday(Builder $query, int $weekday): Builder
    {
        return $query->where($query->getModel()->qualifyColumn('weekday'), $weekday);
    }

    public function scopeForTeacher(
        Builder $query,
        int $teacherId,
        bool $includeSubstitutions = true
    ): Builder {
        return $query->where(function (Builder $teacherQuery) use ($teacherId, $includeSubstitutions) {
            $teacherQuery->where('teacher_id', $teacherId);

            if ($includeSubstitutions) {
                $teacherQuery->orWhere(function (Builder $substitutionQuery) use ($teacherId) {
                    $substitutionQuery
                        ->where('is_substitution', true)
                        ->where('substitute_teacher_id', $teacherId);
                });
            }
        });
    }

    public function scopeForRoom(Builder $query, int $roomId): Builder
    {
        return $query->where('room_id', $roomId);
    }

    public function scopeForSubscription(Builder $query, int $subscriptionId): Builder
    {
        return $query->whereHas(
            'weeklyTimetable',
            fn (Builder $timetableQuery) => $timetableQuery->where('subscription_id', $subscriptionId)
        );
    }

    public function scopeForSlot(
        Builder $query,
        int $weekday,
        int $schoolBellId
    ): Builder {
        return $query
            ->forWeekday($weekday)
            ->where('school_bell_id', $schoolBellId);
    }

    public function effectiveTeacherId(): ?int
    {
        if ($this->is_substitution && $this->substitute_teacher_id !== null) {
            return $this->substitute_teacher_id;
        }

        return $this->teacher_id;
    }

    public function isAssignedToTeacher(int $teacherId): bool
    {
        return $this->effectiveTeacherId() === $teacherId;
    }
}