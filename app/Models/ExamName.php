<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExamName extends Model
{
    protected $fillable = [
        'name',
        'tentative_date',
        'is_active',
    ];

    protected $casts = [
        'tentative_date' => 'date',
        'is_active' => 'boolean',
    ];

}
