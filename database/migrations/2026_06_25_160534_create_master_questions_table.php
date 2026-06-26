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
        Schema::create('master_questions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('question_bank_package_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            $table->foreignId('grade_id')->constrained()->cascadeOnDelete();

            $table->foreignId('stream_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();

            $table->foreignId('lesson_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            $table->foreignId('question_type_master_id')
                ->constrained('question_type_masters')
                ->cascadeOnDelete();

            $table->longText('question');
            $table->string('difficulty')->default('medium');
            $table->string('bloom_level')->nullable();

            $table->decimal('marks', 8, 2)->default(1);
            $table->longText('answer')->nullable();
            $table->longText('explanation')->nullable();

            $table->string('language')->default('en');
            $table->string('source')->default('platform');

            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->index([
                'grade_id',
                'stream_id',
                'subject_id',
                'question_type_master_id',
            ], 'master_questions_filter_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('master_questions');
    }
};
