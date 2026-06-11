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

            $table->foreignId('grade_id')->constrained()->cascadeOnDelete();

            $table->foreignId('stream_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lesson_id')->nullable()->constrained()->nullOnDelete();

            $table->foreignId('question_type_master_id')->constrained()->cascadeOnDelete();
            $table->longText('question');
            $table->enum('difficulty', [
                'easy',
                'medium',
                'hard'
            ])->default('medium');

            $table->enum('bloom_level', [
                'remember',
                'understand',
                'apply',
                'analyze',
                'evaluate',
                'create'
            ])->nullable();

            $table->decimal('marks', 8, 2)->default(1);

            $table->text('answer')->nullable();

            $table->enum('status', [
                'pending',
                'approved',
                'rejected'
            ])
                ->default('approved');

            $table->text('rejection_reason')
                ->nullable();

            $table->foreignId('approved_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('approved_at')
                ->nullable();

            $table->boolean('is_active')->default(true);

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

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
