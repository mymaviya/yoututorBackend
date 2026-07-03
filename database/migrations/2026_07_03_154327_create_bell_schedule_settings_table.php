<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bell_schedule_settings', function (Blueprint $table) {
            $table->id();

            $table->string('name')->default('Default Bell Schedule');

            /*
            |--------------------------------------------------------------------------
            | Main Timings
            |--------------------------------------------------------------------------
            */

            $table->time('assembly_bell_time');
            $table->time('school_over_time');

            /*
            |--------------------------------------------------------------------------
            | Period Rules
            |--------------------------------------------------------------------------
            */

            $table->unsignedTinyInteger('total_periods')->default(7);

            $table->boolean('auto_calculate_period_duration')->default(true);

            $table->unsignedSmallInteger('first_period_duration')
                ->nullable();

            $table->unsignedSmallInteger('regular_period_duration')
                ->nullable();

            // First period is always longer than other periods
            $table->unsignedSmallInteger('first_period_extra_minutes')
                ->default(5);

            /*
            |--------------------------------------------------------------------------
            | Arrival Rules
            |--------------------------------------------------------------------------
            */

            $table->unsignedSmallInteger('teacher_arrival_before_assembly')
                ->default(30);

            $table->unsignedSmallInteger('student_arrival_before_assembly')
                ->default(10);

            /*
            |--------------------------------------------------------------------------
            | Assembly
            |--------------------------------------------------------------------------
            */

            $table->unsignedSmallInteger('assembly_duration')
                ->default(10);

            /*
            |--------------------------------------------------------------------------
            | Break Rules
            |--------------------------------------------------------------------------
            */

            $table->enum('break_mode', [
                'none',
                'short_only',
                'long_only',
                'short_and_long',
            ])->default('long_only');

            $table->unsignedTinyInteger('short_break_after_period')
                ->nullable();

            $table->unsignedSmallInteger('short_break_duration')
                ->default(15);

            $table->unsignedTinyInteger('long_break_after_period')
                ->nullable();

            $table->unsignedSmallInteger('long_break_duration')
                ->default(20);

            // Gap before period starts after break
            $table->unsignedSmallInteger('period_after_break_gap')
                ->default(5);

            /*
            |--------------------------------------------------------------------------
            | Dispersal Rules
            |--------------------------------------------------------------------------
            */

            $table->boolean('bus_dispersal_enabled')
                ->default(true);

            $table->unsignedSmallInteger('bus_dispersal_duration')
                ->default(10);

            $table->unsignedSmallInteger('teacher_dispersal_after_school_over')
                ->default(90);

            /*
            |--------------------------------------------------------------------------
            | Misc
            |--------------------------------------------------------------------------
            */

            $table->date('effective_from')
                ->nullable();

            $table->boolean('is_active')
                ->default(true);

            $table->timestamps();

            $table->index([
                'is_active',
                'effective_from',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bell_schedule_settings');
    }
};