<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LicenseKey extends Model
{
    protected $fillable = [
        'subscription_id',
        'license_key',
        'domain',
        'status',
        'activated_at',
        'expires_at',
    ];

    protected $casts = [
        'activated_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function subscription()
    {
        return $this->belongsTo(Subscription::class);
    }
}