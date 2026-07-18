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
        Schema::create('rooms', function (Blueprint $table) {
            $table->id();

            $table->foreignId('subscription_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('name');
            $table->string('room_no')->nullable();

            $table->enum('type', [
                'classroom',
                'science_lab',
                'computer_lab',
                'library',
                'music_room',
                'sports_ground',
                'activity_room',
                'staff_room',
                'other',
            ])->default('classroom');

            $table->unsignedSmallInteger('capacity')->default(50);

            $table->unsignedSmallInteger('total_rows')->nullable();
            $table->unsignedSmallInteger('total_columns')->nullable();
            $table->unsignedSmallInteger('total_seats')->nullable();
            $table->unsignedSmallInteger('exam_usable_seats')->nullable();

            $table->boolean('allow_exam_seating')->default(true);
            $table->boolean('is_shared')->default(false);
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->unique(
                ['subscription_id', 'name'],
                'uniq_room_school_name'
            );

            $table->index(
                ['subscription_id', 'type', 'is_active'],
                'idx_room_school_type'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rooms');
    }
};
