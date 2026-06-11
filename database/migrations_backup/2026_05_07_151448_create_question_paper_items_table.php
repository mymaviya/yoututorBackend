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
        Schema::create('question_paper_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('question_paper_id')->constrained()->cascadeOnDelete();

            $table->foreignId('question_id')->constrained()->cascadeOnDelete();

            /* QUESTION SETTINGS */

            $table->decimal('marks', 5, 2)->default(1);

            $table->integer('sort_order')->default(1);

            /* SECTION */

            $table->string('section')->nullable();  // Section A/B/C

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('question_paper_items');
    }
};
