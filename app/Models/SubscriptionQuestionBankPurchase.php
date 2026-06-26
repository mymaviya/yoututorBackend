<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubscriptionQuestionBankPurchase extends Model
{
    protected $fillable = [
        'subscription_id',
        'question_bank_package_id',
        'amount',
        'status',
        'starts_at',
        'ends_at',
        'created_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];

    public function subscription()
    {
        return $this->belongsTo(Subscription::class);
    }

    public function package()
    {
        return $this->belongsTo(QuestionBankPackage::class, 'question_bank_package_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}