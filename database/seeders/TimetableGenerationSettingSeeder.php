<?php

namespace Database\Seeders;

use App\Models\TimetableGenerationSetting;
use Illuminate\Database\Seeder;

class TimetableGenerationSettingSeeder extends Seeder
{
    public function run(): void
    {
        TimetableGenerationSetting::updateOrCreate(
            ['name' => 'Default Timetable Rules'],
            [
                'major_subject_daily_required' => true,
                'minor_subject_saturday_preference' => true,
                'class_teacher_first_period' => true,

                'double_period_min_weekly_periods' => 8,
                'max_consecutive_periods' => 2,
                'max_same_subject_per_day' => 2,

                'prefer_minor_last_period' => true,
                'prefer_math_morning' => true,
                'avoid_major_last_period' => false,

                'allow_parallel_subjects' => true,
                'allow_stream_parallel_groups' => true,

                'teacher_clash_check' => true,
                'room_clash_check' => true,

                'is_active' => true,
            ]
        );
    }
}