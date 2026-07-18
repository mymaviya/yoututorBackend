<?php

namespace Database\Seeders;

use App\Models\Subscription;
use App\Models\TimetableTemplate;
use Illuminate\Database\Seeder;

class TimetableTemplateSeeder extends Seeder
{
    public function run(): void
    {
        foreach (Subscription::all() as $subscription) {
            TimetableTemplate::updateOrCreate(
                [
                    'subscription_id' => $subscription->id,
                    'name' => 'Regular Timetable',
                ],
                [
                    'type' => 'regular',
                    'effective_from' => now()->toDateString(),
                    'effective_to' => null,
                    'is_default' => true,
                    'is_active' => true,
                ]
            );
        }
    }
}