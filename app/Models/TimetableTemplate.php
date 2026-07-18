<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSubscription;
use Illuminate\Database\Eloquent\Model;

class TimetableTemplate extends Model
{
    use BelongsToSubscription;

    protected $fillable = [
        'subscription_id',
        'name',
        'type',
        'effective_from',
        'effective_to',
        'is_default',
        'is_active',
    ];

    protected $casts = [
        'effective_from' => 'date',
        'effective_to' => 'date',
        'is_default' => 'boolean',
        'is_active' => 'boolean',
    ];
}