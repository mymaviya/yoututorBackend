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
        Schema::create('questions', function (Blueprint $table) {

            $table->id();

            // HIERARCHY

            $table->foreignId('grade_id')->constrained('grades')->cascadeOnDelete();

            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();

            $table->foreignId('lesson_id')->constrained()->cascadeOnDelete();

            // Questions

            // Rich Text HTML
            $table->longText('question');

            // Main figure/image
            $table->string('question_image')->nullable();

            /* QUESTION TYPE */

            $table->enum('type', [
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

            /* DIFFICULTY LEVEL */

            $table->enum('difficulty', [
                'easy',
                'medium',
                'hard'
            ])->default('medium');

            /* BLOOM TAXONOMY */

            $table->enum('bloom_level', [
                'remember',
                'understand',
                'apply',
                'analyze',
                'evaluate',
                'create'
            ])->nullable();

            /* MARKS */

            $table->decimal('marks', 5, 2)->default(1);

            /* ANSWER */

            // Rich text answer
            $table->longText('answer')->nullable();

            // Detailed explanation
            $table->longText('explanation')->nullable();

            /* SETTINGS */

            // Can appear in exam
            $table->boolean('is_active')->default(true);

            // Featured / important
            $table->boolean('is_featured')->default(false);

            // Created by
            $table->string('created_by')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('questions');
    }
};
