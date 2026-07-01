<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_generated_questions', function (Blueprint $table) {
            if (! Schema::hasColumn('ai_generated_questions', 'regenerated_preview')) {
                $table->json('regenerated_preview')->nullable()->after('match_pairs');
            }
        });
    }

    public function down(): void
    {
        Schema::table('ai_generated_questions', function (Blueprint $table) {
            if (Schema::hasColumn('ai_generated_questions', 'regenerated_preview')) {
                $table->dropColumn('regenerated_preview');
            }
        });
    }
};