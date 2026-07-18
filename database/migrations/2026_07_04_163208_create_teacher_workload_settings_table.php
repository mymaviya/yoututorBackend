<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('teacher_workload_settings', function (Blueprint $table) {
            $table->id();

            $table->foreignId('subscription_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('teacher_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->unsignedTinyInteger('max_periods_per_day')->default(6);
            $table->unsignedTinyInteger('max_periods_per_week')->default(36);
            $table->unsignedTinyInteger('max_consecutive_periods')->default(3);

            $table->unsignedTinyInteger('min_free_periods_per_day')->default(1);

            $table->boolean('allow_first_period')->default(true);
            $table->boolean('allow_last_period')->default(true);
            $table->boolean('is_class_teacher')->default(false);

            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->unique(
                ['subscription_id', 'teacher_id'],
                'uniq_teacher_workload'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('teacher_workload_settings');
    }
};
