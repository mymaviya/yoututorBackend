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

            $table->foreignId('paper_blueprint_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('section_name');

            $table->foreignId('question_type_master_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->enum('difficulty', [
                'easy',
                'medium',
                'hard',
            ])->nullable();

            $table->integer('question_count')->default(0);

            $table->decimal('marks_per_question', 8, 2)->default(1);

            $table->text('instructions')->nullable();

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
