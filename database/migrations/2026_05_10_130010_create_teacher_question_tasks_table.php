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
        Schema::create('teacher_question_tasks', function (Blueprint $table) {

            $table->id();
            $table->foreignId('teacher_id')->constrained()->cascadeOnDelete();
            $table->foreignId('grade_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->enum('question_type', [
                'mcq',
                'multiple_mcq',
                'true_false',
                'fill_blank',
                'short',
                'long',
                'match_column',
                'assertion_reason',
                'numerical'
            ]);
            $table->enum('difficulty', [
                'easy',
                'medium',
                'hard'
            ])->default('medium');
            $table->integer('target_count')->default(0);
            $table->date('due_date')->nullable();
            $table->enum('status', [
                'pending',
                'in_progress',
                'completed'
            ])->default('pending');
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('teacher_question_tasks');
    }
};
