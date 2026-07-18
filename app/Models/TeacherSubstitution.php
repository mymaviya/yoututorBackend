<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Database\Eloquent\SoftDeletes;

class TeacherSubstitution extends Model
{
    use SoftDeletes;

    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_COMPLETED = 'completed';

    protected $fillable = [
        'subscription_id',
        'academic_year_id',
        'teacher_availability_exception_id',
        'timetable_entry_id',

        // Current database column.
        'original_teacher_id',

        // Compatibility only if you later add this column.
        'absent_teacher_id',

        'substitute_teacher_id',

        'grade_id',
        'section_id',
        'subject_id',

        // Compatibility only if you later add this column.
        'school_bell_id',

        'substitution_date',

        'reason',
        'status',
        'remarks',

        'ai_score',
        'ai_reason',
        'is_ai_suggested',
        'ai_suggestions',

        // Current database column.
        'created_by',

        // Compatibility only if you later add these columns.
        'assigned_by',
        'approved_by',
        'approved_at',
        'is_active',
    ];

    protected $casts = [
        'substitution_date' => 'date',
        'approved_at' => 'datetime',
        'ai_score' => 'decimal:2',
        'is_ai_suggested' => 'boolean',
        'is_active' => 'boolean',
        'ai_suggestions' => 'array',
    ];

    protected $appends = [
        'school_bell_id',
        'absent_teacher_id',
        'bell_data',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function availabilityException(): BelongsTo
    {
        return $this->belongsTo(
            TeacherAvailabilityException::class,
            'teacher_availability_exception_id'
        );
    }

    public function timetableEntry(): BelongsTo
    {
        return $this->belongsTo(TimetableEntry::class);
    }

    public function originalTeacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'original_teacher_id');
    }

    /*
     |--------------------------------------------------------------------------
     | Compatibility alias
     |--------------------------------------------------------------------------
     | Your DB uses original_teacher_id, but existing frontend/service code uses
     | absent_teacher. Keep this alias to avoid breaking the UI.
     */
    public function absentTeacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'original_teacher_id');
    }

    public function substituteTeacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'substitute_teacher_id');
    }

    public function grade(): BelongsTo
    {
        return $this->belongsTo(Grade::class);
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    /*
     |--------------------------------------------------------------------------
     | Bell Through Timetable Entry
     |--------------------------------------------------------------------------
     | Your teacher_substitutions table does not have school_bell_id.
     | It comes from timetable_entries.school_bell_id.
     */
    public function bellThroughTimetable(): HasOneThrough
    {
        return $this->hasOneThrough(
            SchoolBell::class,
            TimetableEntry::class,
            'id',
            'id',
            'timetable_entry_id',
            'school_bell_id'
        );
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /*
     |--------------------------------------------------------------------------
     | Compatibility aliases
     |--------------------------------------------------------------------------
     */
    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /*
    |--------------------------------------------------------------------------
    | Accessors
    |--------------------------------------------------------------------------
    */

    public function getSchoolBellIdAttribute(): ?int
    {
        return $this->attributes['school_bell_id']
            ?? $this->timetableEntry?->school_bell_id
            ?? null;
    }

    public function getAbsentTeacherIdAttribute(): ?int
    {
        return $this->attributes['absent_teacher_id']
            ?? $this->original_teacher_id
            ?? null;
    }

    public function getBellDataAttribute(): ?array
    {
        $bell = $this->relationLoaded('timetableEntry')
            ? $this->timetableEntry?->bell
            : null;

        if (! $bell) {
            return null;
        }

        return [
            'id' => $bell->id,
            'title' => $bell->title,
            'period_number' => $bell->period_number,
            'start_time' => $bell->start_time,
            'end_time' => $bell->end_time,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isAssigned(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function isRejected(): bool
    {
        return $this->status === self::STATUS_REJECTED;
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public static function statuses(): array
    {
        return [
            self::STATUS_PENDING,
            self::STATUS_APPROVED,
            self::STATUS_REJECTED,
            self::STATUS_COMPLETED,
        ];
    }
}
