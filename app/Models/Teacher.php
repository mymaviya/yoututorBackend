<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Teacher extends Model
{
    protected $fillable = [
        'user_id',
        'contact',
        'qualification',
        'designation',
        'is_active'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function grades()
    {
        return $this->hasMany(TeacherGrade::class);
    }

    public function assignments()
    {
        return $this->hasMany(TeacherAssignment::class);
    }


}
