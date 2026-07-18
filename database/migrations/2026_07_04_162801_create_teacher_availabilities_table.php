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
        Schema::create('teacher_availabilities', function (Blueprint $table) {
            $table->id();

            $table->foreignId('subscription_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('teacher_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->string('weekday');

            $table->foreignId('school_bell_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->enum('status', [
                'available',
                'busy',
                'leave',
                'meeting',
                'blocked',
            ])->default('available');

            $table->string('reason')->nullable();
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->unique(
                ['subscription_id', 'teacher_id', 'weekday', 'school_bell_id'],
                'uniq_teacher_availability'
            );

            $table->index(
                ['subscription_id', 'weekday', 'school_bell_id'],
                'idx_teacher_availability_day'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('teacher_availabilities');
    }
};
