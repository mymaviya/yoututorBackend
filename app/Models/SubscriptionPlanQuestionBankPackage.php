<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubscriptionPlanQuestionBankPackage extends Model
{
    protected $fillable = [
        'subscription_plan_id',
        'question_bank_package_id',
    ];

    public function plan()
    {
        return $this->belongsTo(SubscriptionPlan::class, 'subscription_plan_id');
    }

    public function package()
    {
        return $this->belongsTo(QuestionBankPackage::class, 'question_bank_package_id');
    }
}