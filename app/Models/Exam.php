<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSubscription;
use Illuminate\Database\Eloquent\Model;

class Exam extends Model
{
    use BelongsToSubscription;

    protected $fillable = [
        
        'subscription_id','exam_name_id',
        'grade_id',
        'stream_id',
        'subject_id',
        'exam_date',
        'max_marks',
        'passing_marks',
        'is_active',
    ];

    protected $casts = [
        'exam_date' => 'date',
        'max_marks' => 'decimal:2',
        'passing_marks' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function examName()
    {
        return $this->belongsTo(ExamName::class);
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

    public function students()
    {
        return $this->belongsToMany(Student::class, 'exam_students')->withPivot('status')->withTimestamps();
    }

    public function answers()
    {
        return $this->hasMany(StudentAnswer::class);
    }

    public function results()
    {
        return $this->hasMany(Result::class);
    }

    public function subscription()
    {
        return $this->belongsTo(Subscription::class);
    }

}
