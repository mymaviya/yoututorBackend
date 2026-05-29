<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserDevice extends Model
{
    protected $fillable = [
        'user_id',
        'device_id',
        'device_name',
        'browser',
        'platform',
        'ip_address',
        'user_agent',
        'is_trusted',
        'last_used_at',
    ];

    protected $casts = [
        'is_trusted' => 'boolean',
        'last_used_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
