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
        Schema::create('question_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grade_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->string('name'); // MCQ, Difficult Words, Word Meaning
            $table->string('slug')->nullable(); // mcq, difficult_words
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['grade_id', 'subject_id', 'name'],'question_types_grade_subject_name_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('question_types');
    }
};
