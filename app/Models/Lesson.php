<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Lesson extends Model
{
    protected $fillable = ['title', 'subject_id', 'is_active'];

    public function subject()
    {
        return $this->belongsTo(Subject::class);
    }

}
