<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuestionTypeAssignment extends Model
{
    protected $fillable = [
        'question_type_master_id',
        'grade_id',
        'stream_id',
        'subject_id',
        'is_active',
    ];

    protected $casts = ['is_active' => 'boolean'];

    public function type()
    {
        return $this->belongsTo(QuestionTypeMaster::class, 'question_type_master_id');
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

    public function questionType()
    {
        return $this->belongsTo(QuestionTypeMaster::class, 'question_type_master_id');
    }
}
