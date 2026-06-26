<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuestionBankPackage extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'package_type',
        'grade_group',
        'price',
        'validity_days',
        'description',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'validity_days' => 'integer',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function grades()
    {
        return $this->hasMany(QuestionBankPackageGrade::class);
    }

    public function masterQuestions()
    {
        return $this->hasMany(MasterQuestion::class);
    }

    public function purchases()
    {
        return $this->hasMany(SubscriptionQuestionBankPurchase::class);
    }
}