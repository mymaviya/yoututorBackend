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
        Schema::create('paper_blueprint_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('paper_blueprint_id')->constrained()->cascadeOnDelete();
            $table->string('section_name');
            // Section A, Section B

            $table->text('instructions')->nullable();
            $table->string('question_type');
            $table->string('difficulty')->nullable();
            $table->string('bloom_level')->nullable();
            $table->integer('question_count');
            $table->decimal('marks_per_question', 8, 2);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('paper_blueprint_sections');
    }
};
