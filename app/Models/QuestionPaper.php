<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuestionPaper extends Model
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

    public function questions()
    {
        return $this->hasMany(Question::class);
    }

    public function items()
    {
        return $this->hasMany(QuestionPaperItem::class);
    }
}
