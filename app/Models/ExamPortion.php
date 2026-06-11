<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExamPortion extends Model
{
    protected $fillable = [
        'teacher_id',
        'assigned_by',
        'approved_by',
        'grade_id',
        'stream_id',
        'subject_id',
        'exam_name_id',
        'due_date',
        'status',
        'submitted_at',
        'approved_at',
        'rejection_reason',
    ];

    protected $casts = [
        'due_date' => 'date',
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
    ];

    public function teacher()
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function assignedBy()
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function grade()
    {
        return $this->belongsTo(Grade::class);
    }

    public function stream()
    {
        return $this->belongsTo(Stream::class);
    }

    public function subject()
    {
        return $this->belongsTo(Subject::class);
    }

    public function examName()
    {
        return $this->belongsTo(ExamName::class);
    }

    public function lessons()
    {
        return $this->hasMany(ExamPortionLesson::class);
    }
}
