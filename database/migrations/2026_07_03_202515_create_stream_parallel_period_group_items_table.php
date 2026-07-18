<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parallel_group_items', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('parallel_group_id');

            $table->foreign('parallel_group_id', 'fk_pgi_group')
                ->references('id')
                ->on('parallel_groups')
                ->cascadeOnDelete();

            $table->foreignId('subject_id')
                ->constrained('subjects', 'id', 'fk_pgi_subject')
                ->cascadeOnDelete();

            $table->foreignId('teacher_id')
                ->nullable()
                ->constrained('users', 'id', 'fk_pgi_teacher')
                ->nullOnDelete();

            $table->json('stream_ids')->nullable();

            $table->string('student_group_name')->nullable();

            $table->unsignedTinyInteger('teacher_split_order')->nullable();

            $table->string('room_no')->nullable();

            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->index('parallel_group_id', 'idx_pgi_group');
            $table->index('is_active', 'idx_pgi_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parallel_group_items');
    }
};
