<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teacher_timetable_views', function (Blueprint $table) {
            $table->id();

            $table->foreignId('timetable_entry_id')
                ->constrained('timetable_entries', 'id', 'fk_ttv_entry')
                ->cascadeOnDelete();

            $table->foreignId('teacher_id')
                ->constrained('users', 'id', 'fk_ttv_teacher')
                ->cascadeOnDelete();

            $table->foreignId('grade_id')
                ->constrained('grades', 'id', 'fk_ttv_grade')
                ->cascadeOnDelete();

            $table->foreignId('section_id')
                ->nullable()
                ->constrained('sections', 'id', 'fk_ttv_section')
                ->nullOnDelete();

            $table->foreignId('stream_id')
                ->nullable()
                ->constrained('streams', 'id', 'fk_ttv_stream')
                ->nullOnDelete();

            $table->foreignId('subject_id')
                ->constrained('subjects', 'id', 'fk_ttv_subject')
                ->cascadeOnDelete();

            $table->foreignId('school_bell_id')
                ->constrained('school_bells', 'id', 'fk_ttv_bell')
                ->cascadeOnDelete();

            $table->string('weekday');
            $table->string('room_no')->nullable();
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->index(['teacher_id', 'weekday', 'school_bell_id'], 'idx_ttv_teacher_day_bell');
            $table->index(['grade_id', 'section_id', 'weekday'], 'idx_ttv_grade_section_day');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teacher_timetable_views');
    }
};
