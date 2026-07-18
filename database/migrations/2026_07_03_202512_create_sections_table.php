<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sections', function (Blueprint $table) {

            $table->id();

            $table->foreignId('grade_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('stream_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            $table->string('name', 20);
            // A
            // B
            // C
            // Red
            // Blue

            $table->string('display_name')->nullable();
            // Section A
            // Red House

            $table->unsignedSmallInteger('capacity')->default(50);

            $table->foreignId('class_teacher_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('room_no')->nullable();

            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->unique([
                'grade_id',
                'stream_id',
                'name',
            ], 'grade_stream_section_unique');

            $table->index([
                'grade_id',
                'stream_id',
                'is_active',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sections');
    }
};