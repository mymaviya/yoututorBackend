<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teacher_assignments', function (Blueprint $table) {

            $table->foreignId('academic_year_id')
                ->nullable()
                ->after('subscription_id')
                ->constrained()
                ->nullOnDelete();

            $table->boolean('is_primary_teacher')
                ->default(false)
                ->after('subject_id');

            $table->unsignedTinyInteger('priority')
                ->default(1)
                ->after('is_primary_teacher');

            $table->unsignedTinyInteger('max_periods_per_week')
                ->nullable()
                ->after('priority');

            $table->date('effective_from')
                ->nullable()
                ->after('max_periods_per_week');

            $table->date('effective_to')
                ->nullable()
                ->after('effective_from');

            $table->index(
                ['teacher_id', 'grade_id', 'subject_id'],
                'idx_teacher_grade_subject'
            );

            $table->index(
                ['section_id', 'stream_id'],
                'idx_teacher_section_stream'
            );

            $table->index(
                ['subscription_id', 'academic_year_id'],
                'idx_ta_school_year'
            );
        });
    }

    public function down(): void
    {
        Schema::table('teacher_assignments', function (Blueprint $table) {

            $table->dropIndex('idx_teacher_grade_subject');
            $table->dropIndex('idx_teacher_section_stream');

            $table->dropForeign(['section_id']);

            $table->dropColumn([
                'section_id',
                'is_primary_teacher',
                'priority',
                'max_periods_per_week',
                'effective_from',
                'effective_to',
            ]);
        });
    }
};
