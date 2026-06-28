<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSubscription;
use Illuminate\Database\Eloquent\Model;

class Question extends Model
{
    use BelongsToSubscription;

    protected $fillable = [
        'subscription_id',
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
        'is_ai_generated',
        'ai_generated_question_id',
        'ai_paper_generation_id',
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
        'is_ai_generated' => 'boolean',
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

    public function subscription()
    {
        return $this->belongsTo(Subscription::class);
    }

    public function aiGeneratedQuestion()
    {
        return $this->belongsTo(AiGeneratedQuestion::class, 'ai_generated_question_id');
    }

    public function aiPaperGeneration()
    {
        return $this->belongsTo(AiPaperGeneration::class, 'ai_paper_generation_id');
    }
}
