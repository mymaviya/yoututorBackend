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
        'matches',

        'answer',
        'explanation',

        'is_active',
        'is_featured',

        'created_by',
        'status',
        'approved_by',
        'approved_at',
        'rejection_reason'
    ];

    protected $casts = [
        'matches' => 'array',
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

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function matchPairs()
    {
        return $this->hasMany(QuestionMatchPair::class)->orderBy('sort_order');
    }

    public function questionType()
    {
        return $this->belongsTo(QuestionType::class, 'type', 'slug');
    }

    public function languageItems()
    {
        return $this->hasMany(LanguageQuestion::class);
    }

    protected $appends = ['type_name'];

    public function getTypeNameAttribute()
    {
        return $this->questionType?->name ?? $this->type;
    }
}
