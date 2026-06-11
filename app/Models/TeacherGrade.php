<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TeacherGrade extends Model
{
    protected $fillable = [
        'teacher_id',
        'grade_id',
        'stream_id',
        'is_active',
    ];

    protected $casts = ['is_active' => 'boolean'];

    public function teacher()
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function grade()
    {
        return $this->belongsTo(Grade::class);
    }

    public function stream()
    {
        return $this->belongsTo(Stream::class);
    }
}
