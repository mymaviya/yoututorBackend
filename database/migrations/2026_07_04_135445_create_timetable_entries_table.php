<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('timetable_entries', function (Blueprint $table) {
            $table->id();

            $table->foreignId('weekly_timetable_id')
                ->constrained('weekly_timetables', 'id', 'fk_te_weekly')
                ->cascadeOnDelete();

            $table->enum('weekday', [
                'Monday',
                'Tuesday',
                'Wednesday',
                'Thursday',
                'Friday',
                'Saturday',
                'Sunday',
            ]);

            $table->foreignId('school_bell_id')
                ->constrained('school_bells', 'id', 'fk_te_bell')
                ->cascadeOnDelete();

            $table->foreignId('teacher_id')
                ->constrained('users', 'id', 'fk_te_teacher')
                ->cascadeOnDelete();

            $table->foreignId('subject_id')
                ->constrained('subjects', 'id', 'fk_te_subject')
                ->cascadeOnDelete();

            $table->foreignId('lesson_id')
                ->nullable()
                ->constrained('lessons', 'id', 'fk_te_lesson')
                ->nullOnDelete();

            $table->foreignId('parallel_group_id')
                ->nullable()
                ->constrained('parallel_groups', 'id', 'fk_te_parallel_group')
                ->nullOnDelete();

            $table->string('student_group_name')->nullable();
            $table->string('room_no')->nullable();

            $table->boolean('is_parallel')->default(false);
            $table->boolean('is_substitution')->default(false);

            $table->foreignId('substitute_teacher_id')
                ->nullable()
                ->constrained('users', 'id', 'fk_te_sub_teacher')
                ->nullOnDelete();

            $table->string('remarks')->nullable();
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->unique([
                'teacher_id',
                'weekday',
                'school_bell_id',
            ], 'uniq_te_teacher_period');

            $table->index([
                'weekly_timetable_id',
                'weekday',
                'school_bell_id',
            ], 'idx_te_weekly_day_bell');

            $table->index(['subject_id', 'weekday'], 'idx_te_subject_day');
            $table->index(['parallel_group_id'], 'idx_te_parallel_group');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('timetable_entries');
    }
};
