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
        Schema::create('question_type_assignments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('question_type_master_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('grade_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            $table->foreignId('stream_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            $table->foreignId('subject_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            $table->boolean('is_active')->default(true);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('question_type_assignments');
    }
};
