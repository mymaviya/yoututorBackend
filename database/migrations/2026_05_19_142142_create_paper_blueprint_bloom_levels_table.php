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
            $table->foreignId('paper_blueprint_id')->constrained()->cascadeOnDelete();
            $table->string('bloom_level');
            $table->decimal('percentage', 5, 2)->default(0);
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
