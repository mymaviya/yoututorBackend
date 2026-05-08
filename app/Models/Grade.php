<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Grade extends Model
{
    protected $fillable = [
        'name',
        'stream',
        'is_active'
    ];

    public function subjects()
    {
        return $this->hasMany(Subject::class);
    }

}
