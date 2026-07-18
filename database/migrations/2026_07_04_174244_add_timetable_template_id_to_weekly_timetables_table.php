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
            $table->foreignId('timetable_template_id')
                ->nullable()
                ->after('id')
                ->constrained('timetable_templates')
                ->nullOnDelete();

            $table->index(
                ['timetable_template_id', 'is_active'],
                'idx_weekly_template_active'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('weekly_timetables', function (Blueprint $table) {
            $table->dropForeign(['timetable_template_id']);
            $table->dropIndex('idx_weekly_template_active');
            $table->dropColumn('timetable_template_id');
        });
    }
};
