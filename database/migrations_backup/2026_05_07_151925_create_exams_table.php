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
        Schema::create('exams', function (Blueprint $table) {
            $table->id();

            $table->foreignId('question_paper_id')->nullable()->constrained()->nullOnDelete();

            $table->string('title');

            $table->foreignId('grade_id')->constrained('grades');

            $table->foreignId('subject_id')->constrained();

            /* SCHEDULE */

            $table->date('exam_date');

            $table->time('start_time')->nullable();

            $table->time('end_time')->nullable();

            /* SETTINGS */

            $table->boolean('is_online')->default(false);

            $table->boolean('is_published')->default(false);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exams');
    }
};
