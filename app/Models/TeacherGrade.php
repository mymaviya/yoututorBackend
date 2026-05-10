<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TeacherGrade extends Model
{
    protected $fillable = [
        'teacher_id',
        'grade_id'
    ];



    public function teacher()
    {
        return $this->belongsTo(Teacher::class);
    }

    public function grade()
    {
        return $this->belongsTo(Grade::class,'grade_id');
    }

}
