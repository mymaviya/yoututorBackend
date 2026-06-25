<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSubscription;
use Illuminate\Database\Eloquent\Model;

class SubscriptionRenewal extends Model
{
    use BelongsToSubscription;

    protected $fillable = [
        'subscription_id',
        'subscription_plan_id',
        'payment_transaction_id',
        'old_start_date',
        'old_end_date',
        'new_start_date',
        'new_end_date',
        'duration_days',
        'old_amount',
        'renewal_amount',
        'renewal_type',
        'remarks',
        'renewed_by',
    ];

    protected $casts = [
        'old_start_date' => 'date',
        'old_end_date' => 'date',
        'new_start_date' => 'date',
        'new_end_date' => 'date',
        'old_amount' => 'decimal:2',
        'renewal_amount' => 'decimal:2',
    ];

    public function subscription()
    {
        return $this->belongsTo(Subscription::class);
    }

    public function plan()
    {
        return $this->belongsTo(
            SubscriptionPlan::class,
            'subscription_plan_id'
        );
    }

    public function paymentTransaction()
    {
        return $this->belongsTo(
            PaymentTransaction::class
        );
    }

    public function renewedBy()
    {
        return $this->belongsTo(
            User::class,
            'renewed_by'
        );
    }
}