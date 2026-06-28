<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('question_papers', function (Blueprint $table) {
            if (! Schema::hasColumn('question_papers', 'is_ai_generated')) {
                $table->boolean('is_ai_generated')->default(false)->after('status');
            }

            if (! Schema::hasColumn('question_papers', 'ai_paper_generation_id')) {
                $table->foreignId('ai_paper_generation_id')
                    ->nullable()
                    ->after('paper_blueprint_id')
                    ->constrained('ai_paper_generations')
                    ->nullOnDelete();
            }
        });

        Schema::table('question_paper_questions', function (Blueprint $table) {
            if (! Schema::hasColumn('question_paper_questions', 'is_ai_generated')) {
                $table->boolean('is_ai_generated')->default(false)->after('sort_order');
            }

            if (! Schema::hasColumn('question_paper_questions', 'ai_generated_question_id')) {
                $table->foreignId('ai_generated_question_id')
                    ->nullable()
                    ->after('question_id')
                    ->constrained('ai_generated_questions')
                    ->nullOnDelete();
            }
        });

        Schema::table('question_paper_items', function (Blueprint $table) {
            if (! Schema::hasColumn('question_paper_items', 'is_ai_generated')) {
                $table->boolean('is_ai_generated')->default(false)->after('group_no');
            }

            if (! Schema::hasColumn('question_paper_items', 'ai_generated_question_id')) {
                $table->foreignId('ai_generated_question_id')
                    ->nullable()
                    ->after('question_id')
                    ->constrained('ai_generated_questions')
                    ->nullOnDelete();
            }
        });

        Schema::table('questions', function (Blueprint $table) {
            if (! Schema::hasColumn('questions', 'is_ai_generated')) {
                $table->boolean('is_ai_generated')->default(false)->after('is_active');
            }

            if (! Schema::hasColumn('questions', 'ai_generated_question_id')) {
                $table->foreignId('ai_generated_question_id')
                    ->nullable()
                    ->after('is_ai_generated')
                    ->constrained('ai_generated_questions')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('questions', 'ai_paper_generation_id')) {
                $table->foreignId('ai_paper_generation_id')
                    ->nullable()
                    ->after('ai_generated_question_id')
                    ->constrained('ai_paper_generations')
                    ->nullOnDelete();
            }
        });

        Schema::table('ai_paper_generations', function (Blueprint $table) {
            if (! Schema::hasColumn('ai_paper_generations', 'question_paper_id')) {
                $table->foreignId('question_paper_id')
                    ->nullable()
                    ->after('exam_portion_id')
                    ->constrained('question_papers')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('ai_paper_generations', function (Blueprint $table) {
            if (Schema::hasColumn('ai_paper_generations', 'question_paper_id')) {
                $table->dropConstrainedForeignId('question_paper_id');
            }
        });

        Schema::table('questions', function (Blueprint $table) {
            if (Schema::hasColumn('questions', 'ai_paper_generation_id')) {
                $table->dropConstrainedForeignId('ai_paper_generation_id');
            }

            if (Schema::hasColumn('questions', 'ai_generated_question_id')) {
                $table->dropConstrainedForeignId('ai_generated_question_id');
            }

            if (Schema::hasColumn('questions', 'is_ai_generated')) {
                $table->dropColumn('is_ai_generated');
            }
        });

        Schema::table('question_paper_items', function (Blueprint $table) {
            if (Schema::hasColumn('question_paper_items', 'ai_generated_question_id')) {
                $table->dropConstrainedForeignId('ai_generated_question_id');
            }

            if (Schema::hasColumn('question_paper_items', 'is_ai_generated')) {
                $table->dropColumn('is_ai_generated');
            }
        });

        Schema::table('question_paper_questions', function (Blueprint $table) {
            if (Schema::hasColumn('question_paper_questions', 'ai_generated_question_id')) {
                $table->dropConstrainedForeignId('ai_generated_question_id');
            }

            if (Schema::hasColumn('question_paper_questions', 'is_ai_generated')) {
                $table->dropColumn('is_ai_generated');
            }
        });

        Schema::table('question_papers', function (Blueprint $table) {
            if (Schema::hasColumn('question_papers', 'ai_paper_generation_id')) {
                $table->dropConstrainedForeignId('ai_paper_generation_id');
            }

            if (Schema::hasColumn('question_papers', 'is_ai_generated')) {
                $table->dropColumn('is_ai_generated');
            }
        });
    }
};
