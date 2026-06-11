<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Subject extends Model
{
    protected $fillable = ['grade_id', 'stream_id', 'name', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function grade()
    {
        return $this->belongsTo(Grade::class);
    }

    public function stream()
    {
        return $this->belongsTo(Stream::class);
    }

    public function lessons()
    {
        return $this->hasMany(Lesson::class);
    }

    public function questionTypeAssignments()
    {
        return $this->hasMany(QuestionTypeAssignment::class);
    }

    public function questions()
    {
        return $this->hasMany(Question::class);
    }
}
