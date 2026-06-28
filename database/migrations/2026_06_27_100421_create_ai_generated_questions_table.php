<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_generated_questions', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('ai_paper_generation_id');
            $table->unsignedBigInteger('subscription_id');

            $table->unsignedBigInteger('grade_id')->nullable();
            $table->unsignedBigInteger('stream_id')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->unsignedBigInteger('lesson_id')->nullable();
            $table->unsignedBigInteger('question_type_master_id')->nullable();

            $table->longText('question');
            $table->longText('answer')->nullable();
            $table->longText('explanation')->nullable();

            $table->string('difficulty')->default('medium');
            $table->string('bloom_level')->nullable();
            $table->decimal('marks', 8, 2)->default(1);

            $table->json('options')->nullable();
            $table->json('match_pairs')->nullable();

            $table->integer('section_index')->default(0);
            $table->integer('sort_order')->default(0);

            $table->boolean('is_selected')->default(true);
            $table->boolean('saved_to_question_bank')->default(false);
            $table->unsignedBigInteger('question_id')->nullable();

            $table->timestamps();

            $table->foreign('ai_paper_generation_id', 'ai_gq_generation_fk')
                ->references('id')
                ->on('ai_paper_generations')
                ->cascadeOnDelete();

            $table->foreign('subscription_id', 'ai_gq_subscription_fk')
                ->references('id')
                ->on('subscriptions')
                ->cascadeOnDelete();

            $table->foreign('grade_id', 'ai_gq_grade_fk')
                ->references('id')
                ->on('grades')
                ->nullOnDelete();

            $table->foreign('stream_id', 'ai_gq_stream_fk')
                ->references('id')
                ->on('streams')
                ->nullOnDelete();

            $table->foreign('subject_id', 'ai_gq_subject_fk')
                ->references('id')
                ->on('subjects')
                ->nullOnDelete();

            $table->foreign('lesson_id', 'ai_gq_lesson_fk')
                ->references('id')
                ->on('lessons')
                ->nullOnDelete();

            $table->foreign('question_type_master_id', 'ai_gq_type_fk')
                ->references('id')
                ->on('question_type_masters')
                ->nullOnDelete();

            $table->foreign('question_id', 'ai_gq_question_fk')
                ->references('id')
                ->on('questions')
                ->nullOnDelete();

            $table->index('subscription_id', 'ai_gq_subscription_idx');
            $table->index('ai_paper_generation_id', 'ai_gq_generation_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_generated_questions');
    }
};