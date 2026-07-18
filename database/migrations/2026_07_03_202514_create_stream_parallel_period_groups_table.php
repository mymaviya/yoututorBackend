<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parallel_groups', function (Blueprint $table) {
            $table->id();

            $table->foreignId('grade_id')
                ->constrained('grades', 'id', 'fk_pg_grade')
                ->cascadeOnDelete();

            $table->string('name');

            $table->boolean('same_period_required')->default(true);

            $table->boolean('period_number_fixed')->default(false);
            $table->unsignedTinyInteger('preferred_period_number')->nullable();

            $table->unsignedTinyInteger('weekly_periods')->default(1);

            $table->boolean('prefer_morning')->default(false);
            $table->boolean('prefer_last_period')->default(false);
            $table->boolean('prefer_saturday')->default(false);

            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->index(['grade_id', 'is_active'], 'idx_pg_grade_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parallel_groups');
    }
};
