<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Exam extends Model
{
    protected $fillable = [
        'title',
        'subject_id',
        'is_active'
    ];

    public function subject()
    {
        return $this->belongsTo(Subject::class);
    }

    public function questionPapers()
    {
        return $this->hasMany(QuestionPaper::class);
    }

    public function answers()
    {
        return $this->hasMany(StudentAnswer::class);
    }

}
