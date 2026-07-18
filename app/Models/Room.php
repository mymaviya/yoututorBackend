<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSubscription;
use Illuminate\Database\Eloquent\Model;

class Room extends Model
{
    use BelongsToSubscription;

    protected $fillable = [
        'subscription_id',
        'name',
        'room_no',
        'type',
        'capacity',
        'total_rows',
        'total_columns',
        'total_seats',
        'exam_usable_seats',
        'allow_exam_seating',
        'is_shared',
        'is_active',

    ];

    protected $casts = [
        'capacity' => 'integer',
        'allow_exam_seating' => 'boolean',
        'is_shared' => 'boolean',
        'is_active' => 'boolean',
    ];
}
