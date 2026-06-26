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
        Schema::create('question_bank_package_grades', function (Blueprint $table) {
            $table->id();

            $table->foreignId('question_bank_package_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('grade_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('stream_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            $table->timestamps();

            $table->unique([
                'question_bank_package_id',
                'grade_id',
                'stream_id',
            ], 'qbp_grade_stream_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('question_bank_package_grades');
    }
};
