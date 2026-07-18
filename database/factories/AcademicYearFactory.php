<?php

namespace Database\Factories;

use App\Models\AcademicYear;
use App\Models\Subscription;
use Illuminate\Database\Eloquent\Factories\Factory;

class AcademicYearFactory extends Factory
{
    protected $model = AcademicYear::class;

    public function definition(): array
    {
        $startYear = fake()->numberBetween(2020, 2035);

        return [
            'subscription_id' => Subscription::factory(),

            'name' => "{$startYear}-" . ($startYear + 1),

            'start_date' => "{$startYear}-04-01",

            'end_date' => ($startYear + 1) . '-03-31',

            'is_active' => true,
        ];
    }
}