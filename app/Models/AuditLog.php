<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSubscription;
use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    use BelongsToSubscription;

    protected $fillable = [
        
        'subscription_id','user_id',
        'action',
        'platform',
        'browser',
        'description',
        'module',
        'auditable_type',
        'auditable_id',
        'old_values',
        'new_values',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function auditable()
    {
        return $this->morphTo();
    }

    public function subscription()
    {
        return $this->belongsTo(Subscription::class);
    }

}
