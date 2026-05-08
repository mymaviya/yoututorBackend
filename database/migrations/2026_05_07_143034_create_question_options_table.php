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
        Schema::create('question_options', function (Blueprint $table) {
            $table->id();

            $table->foreignId('question_id')
                ->constrained()
                ->cascadeOnDelete();

            /* OPTION CONTENT */

            // Rich text HTML
            $table->longText('option_text')->nullable();

            // Option image
            $table->string('option_image')->nullable();

            /* ANSWER */

            $table->boolean('is_correct')->default(false);

            /* ORDER */

            $table->integer('sort_order')->default(1);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */

    public function down(): void
    {
        Schema::dropIfExists('question_options');
    }
};
