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
        Schema::create('question_papers', function (Blueprint $table) {
            $table->id();

            /* BASIC INFO */

            $table->string('title');

            $table->foreignId('grade_id')->constrained('grades');

            $table->foreignId('subject_id')->constrained();

            /* EXAM INFO */

            $table->string('exam_type')->nullable(); // Unit Test / Half Yearly / Final

            $table->integer('duration')->nullable(); // in minutes

            $table->decimal('total_marks', 8, 2)->default(0);

            /* INSTRUCTIONS */

            $table->longText('instructions')->nullable();

            /* SETTINGS */

            $table->boolean('is_published')->default(false);

            /* CREATED BY */

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('question_papers');
    }
};
