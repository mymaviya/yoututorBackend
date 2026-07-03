<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('school_bells', function (Blueprint $table) {
            $table->id();

            $table->string('title');

            $table->enum('type', [
                'teacher_arrival',
                'student_arrival',
                'assembly',
                'period',
                'short_break',
                'long_break',
                'zero_bell',
                'bus_dispersal',
                'student_dispersal',
                'teacher_dispersal',
                'other',
            ])->default('period');

            $table->time('start_time');
            $table->unsignedSmallInteger('duration_minutes');

            $table->time('end_time')->nullable();

            $table->unsignedTinyInteger('period_number')->nullable();

            $table->boolean('is_teaching_period')->default(false);
            $table->boolean('is_break')->default(false);
            $table->boolean('is_dispersal')->default(false);

            $table->date('effective_from')->nullable();

            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);

            $table->timestamps();

            $table->index(['is_active', 'start_time']);
            $table->index(['type', 'is_active']);
            $table->index(['period_number']);
            $table->index(['effective_from']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('school_bells');
    }
};