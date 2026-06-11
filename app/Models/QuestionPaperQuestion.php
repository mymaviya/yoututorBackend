<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuestionPaperQuestion extends Model
{
    protected $fillable = [
        'question_paper_id',
        'question_id',
        'marks',
        'section',
        'instructions',
        'sort_order',
    ];

    protected $casts = ['marks' => 'decimal:2'];

    public function paper()
    {
        return $this->belongsTo(QuestionPaper::class, 'question_paper_id');
    }

    public function question()
    {
        return $this->belongsTo(Question::class);
    }
}
