<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuestionPaper extends Model
{
    protected $fillable = [
        'title',
        'exam_type',
        'duration',
        'instructions',
        'grade_id',
        'subject_id',
        'is_active',
        'total_marks'
    ];

    public function grade()
    {
        return $this->belongsTo(Grade::class, 'grade_id');
    }

    public function subject()
    {
        return $this->belongsTo(Subject::class);
    }

    public function questions()
    {
       return $this->hasMany(QuestionPaperQuestion::class)->orderBy('sort_order');
    }

    public function items()
    {
        return $this->hasMany(QuestionPaperItem::class);
    }



}
