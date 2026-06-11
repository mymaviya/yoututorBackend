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
        Schema::create('paper_blueprint_bloom_levels', function (Blueprint $table) {
            $table->id();

            $table->foreignId('paper_blueprint_section_id')
                ->constrained('paper_blueprint_sections')
                ->cascadeOnDelete();

            $table->enum('bloom_level', [
                'remember',
                'understand',
                'apply',
                'analyze',
                'evaluate',
                'create',
            ]);

            $table->integer('question_count')->default(0);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('paper_blueprint_bloom_levels');
    }
};
