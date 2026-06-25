<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubscriptionPlanFeatureItem extends Model
{
    protected $fillable = [
        'subscription_plan_id',
        'feature_key',
        'is_enabled',
    ];

    protected $casts = [
        'subscription_plan_id' => 'integer',
        'is_enabled' => 'boolean',
    ];

    public function plan()
    {
        return $this->belongsTo(SubscriptionPlan::class, 'subscription_plan_id');
    }
}
