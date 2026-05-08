<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Question extends Model
{
    protected $fillable = [

        'grade_id',
        'subject_id',
        'lesson_id',

        'question',
        'question_image',

        'type',
        'difficulty',
        'bloom_level',

        'marks',

        'answer',
        'explanation',

        'is_active',
        'is_featured',

        'created_by'
    ];

    /*
    |--------------------------------------------------------------------------
    | Relations
    |--------------------------------------------------------------------------
    */

    public function grade()
    {
        return $this->belongsTo(Grade::class);
    }

    public function subject()
    {
        return $this->belongsTo(Subject::class);
    }

    public function lesson()
    {
        return $this->belongsTo(Lesson::class);
    }

    public function options()
    {
        return $this->hasMany(QuestionOption::class);
    }

    public function images()
    {
        return $this->hasMany(QuestionImage::class);
    }

    // public function tags()
    // {
    //     return $this->hasMany(QuestionTag::class);
    // }

}
