<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Subscription extends Model
{
    protected $fillable = [
        'subscription_plan_id',
        'demo_enquiry_id',
        'school_name',
        'contact_person',
        'mobile',
        'email',
        'status',
        'amount',
        'starts_at',
        'ends_at',
        'is_trial',
        'auto_renew',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'starts_at' => 'date',
        'ends_at' => 'date',
        'is_trial' => 'boolean',
        'auto_renew' => 'boolean',
    ];

    public function plan()
    {
        return $this->belongsTo(SubscriptionPlan::class, 'subscription_plan_id');
    }

    public function demoEnquiry()
    {
        return $this->belongsTo(DemoEnquiry::class);
    }

    public function licenseKey()
    {
        return $this->hasOne(LicenseKey::class);
    }

    public function payments()
    {
        return $this->hasMany(PaymentTransaction::class);
    }

    public function isActive(): bool
    {
        return in_array($this->status, ['trial', 'active']) &&
            $this->ends_at &&
            now()->toDateString() <= $this->ends_at->toDateString();
    }

    public function renewals()
    {
        return $this->hasMany(SubscriptionRenewal::class)
            ->latest();
    }
}
