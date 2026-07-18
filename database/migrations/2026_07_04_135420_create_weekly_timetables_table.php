<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('weekly_timetables', function (Blueprint $table) {
            $table->id();

            $table->string('name');

            $table->foreignId('grade_id')
                ->constrained('grades', 'id', 'fk_wt_grade')
                ->cascadeOnDelete();

            $table->foreignId('section_id')
                ->nullable()
                ->constrained('sections', 'id', 'fk_wt_section')
                ->nullOnDelete();

            $table->foreignId('stream_id')
                ->nullable()
                ->constrained('streams', 'id', 'fk_wt_stream')
                ->nullOnDelete();

            $table->date('effective_from')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_generated')->default(false);

            $table->timestamps();

            $table->index(['grade_id', 'section_id', 'stream_id'], 'idx_wt_grade_section_stream');
            $table->index(['is_active', 'effective_from'], 'idx_wt_active_effective');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('weekly_timetables');
    }
};
