<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('timetable_generation_settings', function (Blueprint $table) {
            $table->id();

            $table->string('name')->default('Default Timetable Rules');

            $table->boolean('major_subject_daily_required')->default(true);
            $table->boolean('minor_subject_saturday_preference')->default(true);
            $table->boolean('class_teacher_first_period')->default(true);

            $table->unsignedTinyInteger('double_period_min_weekly_periods')->default(8);
            $table->unsignedTinyInteger('max_consecutive_periods')->default(2);
            $table->unsignedTinyInteger('max_same_subject_per_day')->default(2);

            $table->boolean('prefer_minor_last_period')->default(true);
            $table->boolean('prefer_math_morning')->default(true);
            $table->boolean('avoid_major_last_period')->default(false);

            $table->boolean('allow_parallel_subjects')->default(true);
            $table->boolean('allow_stream_parallel_groups')->default(true);

            $table->boolean('teacher_clash_check')->default(true);
            $table->boolean('room_clash_check')->default(true);

            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->index('is_active', 'idx_tgs_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('timetable_generation_settings');
    }
};
