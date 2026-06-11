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
        Schema::table('question_paper_questions', function (Blueprint $table) {
            $table->decimal('marks', 8, 2)
                ->default(0)
                ->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('question_paper_questions', function (Blueprint $table) {
            $table->integer('marks')->default(1)->change();
        });
    }
};
