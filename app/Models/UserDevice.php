<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSubscription;
use Illuminate\Database\Eloquent\Model;

class UserDevice extends Model
{
    use BelongsToSubscription;

    protected $fillable = [
        
        'subscription_id','user_id',
        'is_trusted',
        'last_used_at',
        'platform',
        'browser',
        'device_id',
        'device_name',
        'device_type',
        'ip_address',
        'user_agent',
        'session_id',
        'last_seen_at',
    ];

    protected $casts = [
        'last_seen_at' => 'datetime',
        'last_used_at' => 'datetime',
        'is_trusted' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function subscription()
    {
        return $this->belongsTo(Subscription::class);
    }

}
