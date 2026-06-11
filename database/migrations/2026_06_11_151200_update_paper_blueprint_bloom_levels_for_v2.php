<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('paper_blueprint_bloom_levels', function (Blueprint $table) {
            $table->decimal('percentage', 5, 2)->default(0)->after('bloom_level');
            $table->unsignedInteger('calculated_count')->default(0)->after('percentage');
        });

        Schema::table('paper_blueprint_bloom_levels', function (Blueprint $table) {
            if (Schema::hasColumn('paper_blueprint_bloom_levels', 'question_count')) {
                $table->dropColumn('question_count');
            }
        });
    }

    public function down(): void
    {
        Schema::table('paper_blueprint_bloom_levels', function (Blueprint $table) {
            $table->integer('question_count')->default(0)->after('bloom_level');
            $table->dropColumn(['percentage', 'calculated_count']);
        });
    }
};
