<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MasterQuestion extends Model
{
    protected $fillable = [
        'question_bank_package_id',
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
        'language',
        'source',
        'is_active',
    ];

    protected $casts = [
        'marks' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function package()
    {
        return $this->belongsTo(QuestionBankPackage::class, 'question_bank_package_id');
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

    public function type()
    {
        return $this->belongsTo(QuestionTypeMaster::class, 'question_type_master_id');
    }
}