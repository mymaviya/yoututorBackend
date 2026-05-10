<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TeacherQuestionTask extends Model
{
     protected $fillable = [
        'teacher_id',
        'grade_id',
        'subject_id',
        'question_type',
        'difficulty',
        'target_count',
        'due_date',
        'status',
        'assigned_by'
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

    public function assignedBy()
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }
}
