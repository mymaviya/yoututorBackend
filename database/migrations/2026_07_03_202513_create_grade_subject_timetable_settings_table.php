<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grade_subject_timetable_settings', function (Blueprint $table) {
            $table->id();

            $table->foreignId('grade_id')
                ->constrained('grades', 'id', 'fk_gsts_grade')
                ->cascadeOnDelete();

            $table->foreignId('subject_id')
                ->constrained('subjects', 'id', 'fk_gsts_subject')
                ->cascadeOnDelete();

            $table->foreignId('teacher_id')
                ->nullable()
                ->constrained('users', 'id', 'fk_gsts_teacher')
                ->nullOnDelete();

            $table->enum('category', [
                'major',
                'minor',
            ])->default('major');

            $table->unsignedTinyInteger('weekly_periods')->default(6);
            $table->unsignedTinyInteger('max_periods_per_day')->default(2);

            $table->boolean('prefer_double_period')->default(false);
            $table->boolean('prefer_morning')->default(false);
            $table->boolean('prefer_last_period')->default(false);
            $table->boolean('prefer_saturday')->default(false);

            $table->boolean('is_parallel_subject')->default(false);
            $table->string('parallel_group_code')->nullable();

            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->unique(['grade_id', 'subject_id'], 'uniq_gsts_grade_subject');
            $table->index(['grade_id', 'category'], 'idx_gsts_grade_cat');
            $table->index('parallel_group_code', 'idx_gsts_parallel_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grade_subject_timetable_settings');
    }
};
