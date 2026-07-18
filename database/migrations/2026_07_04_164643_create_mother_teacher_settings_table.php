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
        Schema::create('mother_teacher_settings', function (Blueprint $table) {
            $table->id();

            $table->foreignId('subscription_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('grade_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('section_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            $table->foreignId('mother_teacher_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->unsignedTinyInteger('max_subjects_per_week')->default(5);
            $table->unsignedTinyInteger('max_periods_per_day')->default(5);
            $table->unsignedTinyInteger('max_periods_per_week')->default(25);

            $table->json('excluded_subject_ids')->nullable();
            // Example: Drawing, Games, Computer

            $table->boolean('force_first_period')->default(true);
            $table->boolean('prefer_first_half')->default(true);

            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->unique(
                ['subscription_id', 'grade_id', 'section_id'],
                'uniq_mother_teacher_context'
            );

            $table->index(
                ['subscription_id', 'mother_teacher_id'],
                'idx_mother_teacher'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mother_teacher_settings');
    }
};
