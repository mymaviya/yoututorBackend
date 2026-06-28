<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('master_question_images', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('master_question_id');

            $table->string('image_path');

            $table->timestamps();

            $table->foreign('master_question_id', 'mq_images_question_fk')
                ->references('id')
                ->on('master_questions')
                ->cascadeOnDelete();

            $table->index('master_question_id', 'mq_images_question_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('master_question_images');
    }
};