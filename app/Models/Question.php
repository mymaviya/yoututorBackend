<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Question extends Model
{
    protected $fillable = [
        'grade_id',
        'stream_id',
        'subject_id',
        'lesson_id',
        'question_type_master_id',
        'question',
        'difficulty',
        'bloom_level',
        'marks',
        'answer',
        'explanation',
        'is_active',
        'is_featured',
        'created_by',
        'status',
        'approved_by',
        'approved_at',
        'rejection_reason',
    ];

    protected $casts = [
        'marks' => 'decimal:2',
        'is_active' => 'boolean',
        'is_featured' => 'boolean',
        'approved_at' => 'datetime',
    ];

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

    public function type()
    {
        return $this->belongsTo(QuestionTypeMaster::class, 'question_type_master_id');
    }

    public function options()
    {
        return $this->hasMany(QuestionOption::class)->orderBy('sort_order');
    }

    public function images()
    {
        return $this->hasMany(QuestionImage::class);
    }

    public function matchPairs()
    {
        return $this->hasMany(QuestionMatchPair::class)->orderBy('sort_order');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function languageItems()
    {
        return $this->hasMany(LanguageQuestion::class);
    }
}
