<?php

namespace Database\Seeders;

use App\Models\BellScheduleSetting;
use Illuminate\Database\Seeder;

class BellScheduleSettingSeeder extends Seeder
{
    public function run(): void
    {
        BellScheduleSetting::query()->update([
            'is_active' => false,
        ]);

        BellScheduleSetting::updateOrCreate(
            [
                'name' => 'Default Bell Schedule',
            ],
            [
                /*
                |--------------------------------------------------------------------------
                | Main Timings
                |--------------------------------------------------------------------------
                */

                'assembly_bell_time' => '07:50:00',
                'school_over_time' => '13:00:00',

                /*
                |--------------------------------------------------------------------------
                | Period Rules
                |--------------------------------------------------------------------------
                */

                'total_periods' => 7,

                'auto_calculate_period_duration' => true,

                // Keep null when auto_calculate_period_duration = true.
                // Generator will calculate:
                // first period = regular period + first_period_extra_minutes.
                'first_period_duration' => null,
                'regular_period_duration' => null,

                // First period should always be 5 minutes longer.
                'first_period_extra_minutes' => 5,

                /*
                |--------------------------------------------------------------------------
                | Arrival Rules
                |--------------------------------------------------------------------------
                */

                'teacher_arrival_before_assembly' => 30,
                'student_arrival_before_assembly' => 10,

                /*
                |--------------------------------------------------------------------------
                | Assembly
                |--------------------------------------------------------------------------
                */

                'assembly_duration' => 10,

                /*
                |--------------------------------------------------------------------------
                | Break Rules
                |--------------------------------------------------------------------------
                */

                // Options: none, short_only, long_only, short_and_long
                'break_mode' => 'long_only',

                'short_break_after_period' => null,
                'short_break_duration' => 15,

                'long_break_after_period' => 4,
                'long_break_duration' => 20,

                // Period after any short/long break will start after this gap.
                'period_after_break_gap' => 5,

                /*
                |--------------------------------------------------------------------------
                | Dispersal Rules
                |--------------------------------------------------------------------------
                */

                'bus_dispersal_enabled' => true,
                'bus_dispersal_duration' => 10,

                // 1 hour 30 minutes after school over.
                'teacher_dispersal_after_school_over' => 90,

                /*
                |--------------------------------------------------------------------------
                | Status
                |--------------------------------------------------------------------------
                */

                'effective_from' => now()->toDateString(),
                'is_active' => true,
            ]
        );
    }
}
