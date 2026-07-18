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
        Schema::table('weekly_timetables', function (Blueprint $table) {
            $table->foreignId('academic_year_id')
                ->nullable()
                ->after('timetable_template_id')
                ->constrained()
                ->nullOnDelete();

            $table->index(
                ['grade_id', 'section_id', 'academic_year_id'],
                'idx_weekly_grade_year'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('weekly_timetables', function (Blueprint $table) {
            //
        });
    }
};
