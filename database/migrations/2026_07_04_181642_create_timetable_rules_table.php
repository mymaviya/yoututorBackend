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
        Schema::create('timetable_rules', function (Blueprint $table) {
            $table->id();

            $table->foreignId('subscription_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('academic_year_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            $table->string('rule_key', 100);

            $table->text('rule_value')->nullable();

            $table->enum('value_type', [
                'boolean',
                'integer',
                'decimal',
                'string',
                'json',
            ])->default('string');

            $table->text('description')->nullable();

            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->unique(
                ['subscription_id', 'academic_year_id', 'rule_key'],
                'uniq_tt_rule'
            );

            $table->index(
                ['subscription_id', 'is_active'],
                'idx_tt_rule_school'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('timetable_rules');
    }
};
