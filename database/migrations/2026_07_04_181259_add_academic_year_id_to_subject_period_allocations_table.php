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
        Schema::table('subject_period_allocations', function (Blueprint $table) {
            $table->foreignId('academic_year_id')
                ->nullable()
                ->after('subscription_id')
                ->constrained()
                ->nullOnDelete();

            $table->index(
                ['subscription_id', 'academic_year_id'],
                'idx_spa_school_year'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subject_period_allocations', function (Blueprint $table) {
            //
        });
    }
};
