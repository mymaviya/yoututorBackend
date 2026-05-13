<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExamPortionLesson extends Model
{
    protected $fillable = [
        'exam_portion_id',
        'lesson_id',
        'topics',
        'learning_objectives',
        'remarks'
    ];

    public function portion()
    {
        return $this->belongsTo(ExamPortion::class, 'exam_portion_id');
    }

    public function lesson()
    {
        return $this->belongsTo(Lesson::class);
    }
}
