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
        'room_no',
        'is_parallel',
        'is_substitution',
        'substitute_teacher_id',
        'remarks',
        'is_active',
    ];

    protected $casts = [
        'id' => 'integer',
        'weekly_timetable_id' => 'integer',
        'school_bell_id' => 'integer',
        'teacher_id' => 'integer',
        'subject_id' => 'integer',
        'lesson_id' => 'integer',
        'parallel_group_id' => 'integer',
        'substitute_teacher_id' => 'integer',
        'is_parallel' => 'boolean',
        'is_substitution' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function weeklyTimetable(): BelongsTo
    {
        return $this->belongsTo(
            WeeklyTimetable::class,
            'weekly_timetable_id'
        );
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
        return $this->belongsTo(
            ParallelGroup::class,
            'parallel_group_id'
        );
    }

    public function substituteTeacher(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'substitute_teacher_id'
        );
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeForWeekday(
        Builder $query,
        string $weekday
    ): Builder {
        return $query->where('weekday', $weekday);
    }

    public function scopeForTeacher(
        Builder $query,
        int $teacherId,
        bool $includeSubstitutions = true
    ): Builder {
        return $query->where(function (Builder $teacherQuery) use (
            $teacherId,
            $includeSubstitutions
        ) {
            $teacherQuery->where('teacher_id', $teacherId);

            if ($includeSubstitutions) {
                $teacherQuery->orWhere(function (
                    Builder $substitutionQuery
                ) use ($teacherId) {
                    $substitutionQuery
                        ->where('is_substitution', true)
                        ->where(
                            'substitute_teacher_id',
                            $teacherId
                        );
                });
            }
        });
    }

    public function scopeForSubscription(
        Builder $query,
        int $subscriptionId
    ): Builder {
        return $query->whereHas(
            'weeklyTimetable.template',
            fn (Builder $templateQuery) => $templateQuery->where(
                'subscription_id',
                $subscriptionId
            )
        );
    }

    public function effectiveTeacherId(): ?int
    {
        if (
            $this->is_substitution
            && $this->substitute_teacher_id !== null
        ) {
            return $this->substitute_teacher_id;
        }

        return $this->teacher_id;
    }

    public function isAssignedToTeacher(int $teacherId): bool
    {
        return $this->effectiveTeacherId() === $teacherId;
    }
}