<?php

namespace Database\Factories;

use App\Models\SubscriptionPlan;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class SubscriptionPlanFactory extends Factory
{
    protected $model = SubscriptionPlan::class;

    public function definition(): array
    {
        return [
            'name' => 'Test Plan ' . fake()->unique()->numberBetween(1000, 9999),
            'slug' => 'test-plan-' . Str::lower(Str::random(8)),
            'price' => 999,
            'billing_cycle' => 'yearly',
            'is_active' => true,
        ];
    }
}