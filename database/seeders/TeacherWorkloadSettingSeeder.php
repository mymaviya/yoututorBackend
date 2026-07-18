<?php

namespace Database\Seeders;

use App\Models\Subscription;
use App\Models\TeacherWorkloadSetting;
use App\Models\User;
use Illuminate\Database\Seeder;

class TeacherWorkloadSettingSeeder extends Seeder
{
    public function run(): void
    {
        $defaultSubscriptionId = Subscription::query()->value('id');

        $teachers = User::query()
            ->where(function ($q) {
                $q->where('role', 'teacher')
                    ->orWhereHas('roleData', fn ($role) => $role->where('slug', 'teacher'));
            })
            ->get();

        foreach ($teachers as $teacher) {
            $subscriptionId = $teacher->subscription_id ?: $defaultSubscriptionId;

            if (! $subscriptionId) {
                continue;
            }

            TeacherWorkloadSetting::updateOrCreate(
                [
                    'subscription_id' => $subscriptionId,
                    'teacher_id' => $teacher->id,
                ],
                [
                    'max_periods_per_day' => 6,
                    'max_periods_per_week' => 36,
                    'max_consecutive_periods' => 3,
                    'min_free_periods_per_day' => 1,
                    'allow_first_period' => true,
                    'allow_last_period' => true,
                    'is_class_teacher' => false,
                    'is_active' => true,
                ]
            );
        }
    }
}