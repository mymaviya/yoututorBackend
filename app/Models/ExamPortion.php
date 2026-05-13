<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExamPortion extends Model
{
    protected $fillable = [
        'teacher_id',
        'grade_id',
        'subject_id',
        'exam_name_id',
        'due_date',
        'status',
        'assigned_by',
        'approved_by',
        'submitted_at',
        'approved_at',
        'rejection_reason',
    ];

    public function teacher()
    {
        return $this->belongsTo(Teacher::class);
    }

    public function grade()
    {
        return $this->belongsTo(Grade::class);
    }

    public function subject()
    {
        return $this->belongsTo(Subject::class);
    }

    public function lessons()
    {
        return $this->hasMany(ExamPortionLesson::class);
    }

    public function assignedBy()
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function examName()
    {
        return $this->belongsTo(ExamName::class);
    }
}
