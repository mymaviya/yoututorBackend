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
        Schema::create('student_answers', function (Blueprint $table) {
            $table->id();

            $table->foreignId('exam_id')->constrained()->cascadeOnDelete();

            $table->foreignId('question_id')->constrained()->cascadeOnDelete();

            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();

            /* ANSWER */

            $table->longText('answer')->nullable();

            /* MCQ OPTION */

            $table->foreignId('question_option_id')->nullable()->constrained()->nullOnDelete();

            /* MARKING */

            $table->decimal('marks_awarded', 8, 2)->nullable();

            $table->boolean('is_correct')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_answers');
    }
};
