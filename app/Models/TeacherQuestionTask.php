<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TeacherQuestionTask extends Model
{
    protected $fillable = [
        'teacher_id',
        'assigned_by',
        'grade_id',
        'stream_id',
        'subject_id',
        'lesson_id',
        'question_type_master_id',
        'target_count',
        'completed_count',
        'due_date',
        'status',
        'remarks',
    ];

    protected $casts = [
        'due_date' => 'date',
    ];

    public function teacher()
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function assignedBy()
    {
        return $this->belongsTo(User::class, 'assigned_by');
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

    public function lesson()
    {
        return $this->belongsTo(Lesson::class);
    }

    public function questionType()
    {
        return $this->belongsTo(QuestionTypeMaster::class, 'question_type_master_id');
    }
}
