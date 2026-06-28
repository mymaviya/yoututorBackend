<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('master_question_match_pairs', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('master_question_id');

            $table->string('left_value');
            $table->string('right_value');
            $table->integer('sort_order')->default(0);

            $table->timestamps();

            $table->foreign('master_question_id', 'mq_pairs_question_fk')
                ->references('id')
                ->on('master_questions')
                ->cascadeOnDelete();

            $table->index('master_question_id', 'mq_pairs_question_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('master_question_match_pairs');
    }
};