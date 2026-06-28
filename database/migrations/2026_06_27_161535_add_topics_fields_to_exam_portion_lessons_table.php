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
        Schema::table('exam_portion_lessons', function (Blueprint $table) {
            if (!Schema::hasColumn('exam_portion_lessons', 'topics')) {
                $table->text('topics')->nullable()->after('lesson_id');
            }

            if (!Schema::hasColumn('exam_portion_lessons', 'learning_objectives')) {
                $table->text('learning_objectives')->nullable()->after('topics');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('exam_portion_lessons', function (Blueprint $table) {
            //
        });
    }
};
