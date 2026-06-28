<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSubscription;
use Illuminate\Database\Eloquent\Model;

class AiGeneratedQuestion extends Model
{
    use BelongsToSubscription;

    protected $fillable = [
        'ai_paper_generation_id',
        'subscription_id',
        'grade_id',
        'stream_id',
        'subject_id',
        'lesson_id',
        'question_type_master_id',
        'question',
        'answer',
        'explanation',
        'difficulty',
        'bloom_level',
        'marks',
        'options',
        'match_pairs',
        'section_index',
        'sort_order',
        'is_selected',
        'saved_to_question_bank',
        'question_id',
    ];

    protected $casts = [
        'marks' => 'decimal:2',
        'options' => 'array',
        'match_pairs' => 'array',
        'section_index' => 'integer',
        'sort_order' => 'integer',
        'is_selected' => 'boolean',
        'saved_to_question_bank' => 'boolean',
    ];

    public function generation()
    {
        return $this->belongsTo(AiPaperGeneration::class, 'ai_paper_generation_id');
    }

    public function subscription()
    {
        return $this->belongsTo(Subscription::class);
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

    public function savedQuestion()
    {
        return $this->belongsTo(Question::class, 'question_id');
    }
}