<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSubscription;
use Illuminate\Database\Eloquent\Model;

class AiPaperGeneration extends Model
{
    use BelongsToSubscription;

    protected $fillable = [
        'subscription_id',
        'exam_name_id',
        'exam_portion_id',
        'question_paper_id',
        'paper_blueprint_id',
        'created_by',
        'title',
        'status',
        'language',
        'difficulty',
        'total_questions',
        'total_marks',
        'prompt',
        'ai_response',
        'error_message',
        'progress_percentage',
        'current_section',
        'progress_message',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'total_questions' => 'integer',
        'total_marks' => 'decimal:2',
        'progress_percentage' => 'integer',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function subscription()
    {
        return $this->belongsTo(Subscription::class);
    }

    public function examName()
    {
        return $this->belongsTo(ExamName::class, 'exam_name_id');
    }

    public function examPortion()
    {
        return $this->belongsTo(ExamPortion::class, 'exam_portion_id');
    }

    public function blueprint()
    {
        return $this->belongsTo(PaperBlueprint::class, 'paper_blueprint_id');
    }

    public function questionPaper()
    {
        return $this->belongsTo(QuestionPaper::class, 'question_paper_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function questions()
    {
        return $this->hasMany(AiGeneratedQuestion::class);
    }
}
