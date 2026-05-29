<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoginHoliday extends Model
{
    protected $fillable = [
        'date',
        'title',
        'is_active',
    ];

    protected $casts = [
        'date' => 'date',
        'is_active' => 'boolean',
    ];
}
