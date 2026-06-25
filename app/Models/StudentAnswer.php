<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSubscription;
use Illuminate\Database\Eloquent\Model;

class StudentAnswer extends Model
{
    use BelongsToSubscription;

    protected $fillable = [
        
        'subscription_id','exam_id',
        'student_id',
        'question_id',
        'answer',
        'marks_obtained',
        'is_correct',
    ];

    protected $casts = [
        'marks_obtained' => 'decimal:2',
        'is_correct' => 'boolean',
    ];

    public function exam()
    {
        return $this->belongsTo(Exam::class);
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function question()
    {
        return $this->belongsTo(Question::class);
    }

    public function subscription()
    {
        return $this->belongsTo(Subscription::class);
    }

}
