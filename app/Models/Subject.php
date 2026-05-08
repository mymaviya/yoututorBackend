<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Subject extends Model
{
    protected $fillable = ['name', 'grade_id', 'is_active'];

    public function grade()
    {
        return $this->belongsTo(Grade::class, 'grade_id');
    }

    public function lessons()
    {
        return $this->hasMany(Lesson::class);
    }
}
