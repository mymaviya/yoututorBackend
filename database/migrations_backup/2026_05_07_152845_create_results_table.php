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
        Schema::create('results', function (Blueprint $table) {

            $table->id();

            $table->foreignId('exam_id')->constrained()->cascadeOnDelete();

            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();

            /* MARKS */

            $table->decimal('total_marks', 8, 2);
            $table->decimal('obtained_marks', 8, 2);
            $table->decimal('percentage', 5, 2);

            /* GRADE*/

            $table->string('grade')->nullable();

            /* RESULT STATUS */

            $table->enum('result_status', [
                'pass',
                'fail'
            ]);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('results');
    }
};
