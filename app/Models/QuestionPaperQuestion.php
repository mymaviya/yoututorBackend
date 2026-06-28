<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuestionPaperQuestion extends Model
{
    protected $fillable = [
        'question_paper_id',
        'question_id',
        'ai_generated_question_id',
        'marks',
        'section',
        'instructions',
        'sort_order',
        'is_ai_generated',
    ];

    protected $casts = [
        'marks' => 'decimal:2',
        'is_ai_generated' => 'boolean',
    ];

    public function paper()
    {
        return $this->belongsTo(QuestionPaper::class, 'question_paper_id');
    }

    public function question()
    {
        return $this->belongsTo(Question::class);
    }

    public function aiGeneratedQuestion()
    {
        return $this->belongsTo(AiGeneratedQuestion::class, 'ai_generated_question_id');
    }
}
