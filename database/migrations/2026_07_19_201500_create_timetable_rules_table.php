<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('timetable_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_year_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('grade_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('section_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('stream_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('subject_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('teacher_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('school_bell_id')->nullable()->constrained()->nullOnDelete();

            $table->string('name', 150);
            $table->string('rule_type', 60);
            $table->enum('constraint_type', ['hard', 'soft'])->default('soft');
            $table->unsignedTinyInteger('weekday')->nullable();
            $table->json('configuration')->nullable();
            $table->unsignedTinyInteger('priority')->default(5);
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(
                ['subscription_id', 'academic_year_id', 'is_active'],
                'tt_rules_subscription_year_active_index'
            );
            $table->index(
                ['subscription_id', 'rule_type', 'constraint_type'],
                'tt_rules_type_constraint_index'
            );
            $table->index(
                ['teacher_id', 'subject_id', 'grade_id'],
                'tt_rules_target_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('timetable_rules');
    }
};
