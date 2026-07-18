<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class SubscriptionPlan extends Model
{
    use HasFactory;
    
    protected $fillable = [
        'name',
        'slug',
        'monthly_display_price',
        'yearly_price',
        'yearly_saving',
        'duration_days',
        'trial_days',
        'features',
        'is_trial',
        'is_popular',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'monthly_display_price' => 'decimal:2',
        'yearly_price' => 'decimal:2',
        'yearly_saving' => 'decimal:2',
        'duration_days' => 'integer',
        'trial_days' => 'integer',
        'features' => 'array',
        'is_trial' => 'boolean',
        'is_popular' => 'boolean',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function subscriptions()
    {
        return $this->hasMany(Subscription::class);
    }

    public function featureItems()
    {
        return $this->hasMany(SubscriptionPlanFeatureItem::class);
    }

    public function enabledFeatureItems()
    {
        return $this->hasMany(SubscriptionPlanFeatureItem::class)
            ->where('is_enabled', true);
    }

    public function hasFeature(string $featureKey): bool
    {
        return $this->featureItems()
            ->where('feature_key', $featureKey)
            ->where('is_enabled', true)
            ->exists();
    }

    public function questionBankPackages()
    {
        return $this->belongsToMany(
            QuestionBankPackage::class,
            'subscription_plan_question_bank_packages',
            'subscription_plan_id',
            'question_bank_package_id'
        )->withTimestamps();
    }
}
