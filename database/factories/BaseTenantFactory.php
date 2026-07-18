<?php

namespace Database\Factories;

use App\Models\Subscription;
use Illuminate\Database\Eloquent\Factories\Factory;

abstract class BaseTenantFactory extends Factory
{
    protected function tenantAttributes(): array
    {
        return [
            'subscription_id' => Subscription::factory(),
        ];
    }
}