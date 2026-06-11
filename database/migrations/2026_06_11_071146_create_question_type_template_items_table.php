<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('question_type_template_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('question_type_template_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('question_type_master_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->timestamps();

            $table->unique(
                ['question_type_template_id', 'question_type_master_id'],
                'qt_template_item_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('question_type_template_items');
    }
};