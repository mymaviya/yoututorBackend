<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teacher_substitutions', function (Blueprint $table) {
            if (! Schema::hasColumn('teacher_substitutions', 'subscription_id')) {
                $table->foreignId('subscription_id')
                    ->nullable()
                    ->after('id')
                    ->constrained()
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('teacher_substitutions', 'academic_year_id')) {
                $table->foreignId('academic_year_id')
                    ->nullable()
                    ->after('subscription_id')
                    ->constrained()
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('teacher_substitutions', 'teacher_availability_exception_id')) {
                $table->foreignId('teacher_availability_exception_id')
                    ->nullable()
                    ->after('academic_year_id')
                    ->constrained('teacher_availability_exceptions')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('teacher_substitutions', 'grade_id')) {
                $table->foreignId('grade_id')
                    ->nullable()
                    ->after('substitute_teacher_id')
                    ->constrained()
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('teacher_substitutions', 'section_id')) {
                $table->foreignId('section_id')
                    ->nullable()
                    ->after('grade_id')
                    ->constrained()
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('teacher_substitutions', 'subject_id')) {
                $table->foreignId('subject_id')
                    ->nullable()
                    ->after('section_id')
                    ->constrained()
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('teacher_substitutions', 'ai_score')) {
                $table->decimal('ai_score', 5, 2)
                    ->nullable()
                    ->after('remarks');
            }

            if (! Schema::hasColumn('teacher_substitutions', 'ai_reason')) {
                $table->text('ai_reason')
                    ->nullable()
                    ->after('ai_score');
            }

            if (! Schema::hasColumn('teacher_substitutions', 'is_ai_suggested')) {
                $table->boolean('is_ai_suggested')
                    ->default(false)
                    ->after('ai_reason');
            }

            if (! Schema::hasColumn('teacher_substitutions', 'deleted_at')) {
                $table->softDeletes();
            }
        });
    }

    public function down(): void
    {
        Schema::table('teacher_substitutions', function (Blueprint $table) {
            if (Schema::hasColumn('teacher_substitutions', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
        });
    }
};