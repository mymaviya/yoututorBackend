<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSubscription;
use Illuminate\Database\Eloquent\Model;

class Student extends Model
{
    use BelongsToSubscription;

    protected $fillable = [
        
        'subscription_id','admission_no',
        'roll_no',
        'name',
        'father_name',
        'mother_name',
        'dob',
        'gender',
        'mobile',
        'email',
        'address',
        'grade_id',
        'stream_id',
        'is_active',
    ];

    protected $casts = [
        'dob' => 'date',
        'is_active' => 'boolean',
    ];

    public function grade()
    {
        return $this->belongsTo(Grade::class);
    }

    public function stream()
    {
        return $this->belongsTo(Stream::class);
    }

    public function exams()
    {
        return $this->belongsToMany(Exam::class, 'exam_students')->withPivot('status')->withTimestamps();
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
