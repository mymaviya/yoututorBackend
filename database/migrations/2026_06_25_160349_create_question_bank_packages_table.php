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
        Schema::create('question_bank_packages', function (Blueprint $table) {
            $table->id();

            $table->string('name');
            $table->string('slug')->unique();

            $table->enum('package_type', [
                'single_grade',
                'grade_group',
                'stream_group',
            ])->default('single_grade');

            $table->string('grade_group')->nullable();

            $table->decimal('price', 10, 2)->default(0);
            $table->integer('validity_days')->nullable();

            $table->text('description')->nullable();

            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('question_bank_packages');
    }
};
