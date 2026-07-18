<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('class_timetable_views', function (Blueprint $table) {
            $table->id();

            $table->foreignId('timetable_entry_id')
                ->constrained('timetable_entries', 'id', 'fk_ctv_entry')
                ->cascadeOnDelete();

            $table->foreignId('grade_id')
                ->constrained('grades', 'id', 'fk_ctv_grade')
                ->cascadeOnDelete();

            $table->foreignId('section_id')
                ->nullable()
                ->constrained('sections', 'id', 'fk_ctv_section')
                ->nullOnDelete();

            $table->foreignId('stream_id')
                ->nullable()
                ->constrained('streams', 'id', 'fk_ctv_stream')
                ->nullOnDelete();

            $table->foreignId('teacher_id')
                ->constrained('users', 'id', 'fk_ctv_teacher')
                ->cascadeOnDelete();

            $table->foreignId('subject_id')
                ->constrained('subjects', 'id', 'fk_ctv_subject')
                ->cascadeOnDelete();

            $table->foreignId('school_bell_id')
                ->constrained('school_bells', 'id', 'fk_ctv_bell')
                ->cascadeOnDelete();

            $table->string('weekday');
            $table->string('student_group_name')->nullable();
            $table->boolean('is_parallel')->default(false);
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->index(['grade_id', 'section_id', 'weekday', 'school_bell_id'], 'idx_ctv_class_day_bell');
            $table->index(['teacher_id', 'weekday', 'school_bell_id'], 'idx_ctv_teacher_day_bell');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('class_timetable_views');
    }
};
