<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSubscription;
use Illuminate\Database\Eloquent\Model;

class Result extends Model
{
    use BelongsToSubscription;

    protected $fillable = [
        
        'subscription_id','exam_id',
        'student_id',
        'total_marks',
        'marks_obtained',
        'percentage',
        'grade',
        'status',
    ];

    protected $casts = [
        'total_marks' => 'decimal:2',
        'marks_obtained' => 'decimal:2',
        'percentage' => 'decimal:2',
    ];

    public function exam()
    {
        return $this->belongsTo(Exam::class);
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function subscription()
    {
        return $this->belongsTo(Subscription::class);
    }

}
