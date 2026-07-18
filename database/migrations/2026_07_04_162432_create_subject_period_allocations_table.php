<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subject_period_allocations', function (Blueprint $table) {
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

            $table->foreignId('stream_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            $table->foreignId('subject_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('preferred_teacher_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->enum('subject_category', [
                'major',
                'minor',
                'language',
                'elective',
                'lab',
                'activity',
            ])->default('major');

            $table->unsignedTinyInteger('weekly_periods')->default(6);
            $table->unsignedTinyInteger('max_periods_per_day')->default(2);

            $table->boolean('prefer_double_period')->default(false);
            $table->boolean('prefer_morning')->default(false);
            $table->boolean('prefer_last_period')->default(false);
            $table->boolean('prefer_saturday')->default(false);

            $table->boolean('is_optional')->default(false);
            $table->boolean('is_parallel_subject')->default(false);

            $table->string('parallel_group_code')->nullable();

            $table->unsignedTinyInteger('priority')->default(5);

            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->unique(
                [
                    'subscription_id',
                    'grade_id',
                    'section_id',
                    'stream_id',
                    'subject_id',
                ],
                'uniq_spa_context_subject'
            );

            $table->index(
                ['subscription_id', 'grade_id', 'section_id', 'stream_id'],
                'idx_spa_context'
            );

            $table->index(
                ['subject_category', 'is_active'],
                'idx_spa_category_active'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subject_period_allocations');
    }
};