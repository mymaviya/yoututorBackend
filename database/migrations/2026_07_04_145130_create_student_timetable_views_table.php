<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_timetable_views', function (Blueprint $table) {
            $table->id();

            $table->foreignId('timetable_entry_id')
                ->constrained('timetable_entries', 'id', 'fk_stv_entry')
                ->cascadeOnDelete();

            $table->foreignId('grade_id')
                ->constrained('grades', 'id', 'fk_stv_grade')
                ->cascadeOnDelete();

            $table->foreignId('section_id')
                ->nullable()
                ->constrained('sections', 'id', 'fk_stv_section')
                ->nullOnDelete();

            $table->foreignId('stream_id')
                ->nullable()
                ->constrained('streams', 'id', 'fk_stv_stream')
                ->nullOnDelete();

            $table->foreignId('subject_id')
                ->constrained('subjects', 'id', 'fk_stv_subject')
                ->cascadeOnDelete();

            $table->foreignId('teacher_id')
                ->constrained('users', 'id', 'fk_stv_teacher')
                ->cascadeOnDelete();

            $table->foreignId('school_bell_id')
                ->constrained('school_bells', 'id', 'fk_stv_bell')
                ->cascadeOnDelete();

            $table->string('weekday');
            $table->string('student_group_name')->nullable();
            $table->boolean('is_parallel')->default(false);
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->index(['grade_id', 'section_id', 'stream_id', 'weekday'], 'idx_stv_class_stream_day');
            $table->index(['school_bell_id', 'weekday'], 'idx_stv_bell_day');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_timetable_views');
    }
};
