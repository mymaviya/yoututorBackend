<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeacherTimetable extends Model
{
    protected $table = 'teacher_timetable_views';

    /**
     * This model reads from a database view, so Eloquent must not attempt
     * to maintain created_at or updated_at columns.
     */
    public $timestamps = false;

    /**
     * The timetable view is read-only. Data must be changed through the
     * underlying TimetableEntry model and timetable-generation workflow.
     */
    protected $guarded = ['*'];

    protected $casts = [
        'id' => 'integer',
        'timetable_entry_id' => 'integer',
        'teacher_id' => 'integer',
        'grade_id' => 'integer',
        'section_id' => 'integer',
        'stream_id' => 'integer',
        'subject_id' => 'integer',
        'school_bell_id' => 'integer',
        'is_active' => 'boolean',
        'is_substitution' => 'boolean',
    ];

    public function timetableEntry(): BelongsTo
    {
        return $this->belongsTo(
            TimetableEntry::class,
            'timetable_entry_id'
        );
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
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

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class, 'subject_id');
    }

    public function bell(): BelongsTo
    {
        return $this->belongsTo(SchoolBell::class, 'school_bell_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
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
                $teacherQuery->orWhereHas(
                    'timetableEntry',
                    fn (Builder $entryQuery) => $entryQuery
                        ->where('is_substitution', true)
                        ->where('substitute_teacher_id', $teacherId)
                );
            }
        });
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
                fn (Builder $builder) => $builder->where(
                    'section_id',
                    $sectionId
                )
            )
            ->when(
                $streamId !== null,
                fn (Builder $builder) => $builder->where(
                    'stream_id',
                    $streamId
                )
            );
    }

    public function scopeForAcademicYear(
        Builder $query,
        int $academicYearId
    ): Builder {
        return $query->whereHas(
            'timetableEntry.weeklyTimetable',
            fn (Builder $weekly) => $weekly->where(
                'academic_year_id',
                $academicYearId
            )
        );
    }

    public function scopeForSubscription(
        Builder $query,
        int $subscriptionId
    ): Builder {
        return $query->whereHas(
            'timetableEntry.weeklyTimetable.template',
            fn (Builder $template) => $template->where(
                'subscription_id',
                $subscriptionId
            )
        );
    }
}