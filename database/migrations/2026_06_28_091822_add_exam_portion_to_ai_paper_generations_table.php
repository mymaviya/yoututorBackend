<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_paper_generations', function (Blueprint $table) {
            if (! Schema::hasColumn('ai_paper_generations', 'exam_name_id')) {
                $table->foreignId('exam_name_id')
                    ->nullable()
                    ->after('paper_blueprint_id')
                    ->constrained('exam_names')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('ai_paper_generations', 'exam_portion_id')) {
                $table->foreignId('exam_portion_id')
                    ->nullable()
                    ->after('exam_name_id')
                    ->constrained('exam_portions')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('ai_paper_generations', function (Blueprint $table) {
            if (Schema::hasColumn('ai_paper_generations', 'exam_portion_id')) {
                $table->dropConstrainedForeignId('exam_portion_id');
            }

            if (Schema::hasColumn('ai_paper_generations', 'exam_name_id')) {
                $table->dropConstrainedForeignId('exam_name_id');
            }
        });
    }
};