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
        Schema::create('exam_students', function (Blueprint $table) {
            $table->id();

            $table->foreignId('exam_id')->constrained()->cascadeOnDelete();

            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();

            /* STATUS */

            $table->enum('status', [
                'pending',
                'appeared',
                'submitted',
                'absent'
            ])->default('pending');

            /* MARKS */

            $table->decimal('obtained_marks', 8, 2)->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exam_students');
    }
};
