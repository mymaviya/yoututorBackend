<?php

namespace Database\Seeders;

use App\Models\BellScheduleSetting;
use App\Services\BellScheduleGeneratorService;
use Illuminate\Database\Seeder;

class SchoolBellSeeder extends Seeder
{
    public function run(): void
    {
        $setting = BellScheduleSetting::query()
            ->where('is_active', true)
            ->latest('id')
            ->first();

        if (! $setting) {
            $this->call(BellScheduleSettingSeeder::class);

            $setting = BellScheduleSetting::query()
                ->where('is_active', true)
                ->latest('id')
                ->first();
        }

        if (! $setting) {
            return;
        }

        app(BellScheduleGeneratorService::class)
            ->generate($setting);
    }
}
