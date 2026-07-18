<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('question_papers', function (Blueprint $table) {

            if (!Schema::hasColumn('question_papers', 'instructions')) {
                $table->longText('instructions')->nullable()->after('duration_minutes');
            }

            if (!Schema::hasColumn('question_papers', 'exam_type')) {
                $table->string('exam_type')->nullable()->after('paper_blueprint_id');
            }

            if (!Schema::hasColumn('question_papers', 'is_published')) {
                $table->boolean('is_published')->default(false)->after('instructions');
            }

            if (!Schema::hasColumn('question_papers', 'finalized_at')) {
                $table->timestamp('finalized_at')->nullable();
            }

            if (!Schema::hasColumn('question_papers', 'finalized_by')) {
                $table->foreignId('finalized_by')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();
            }

            if (!Schema::hasColumn('question_papers', 'printed_at')) {
                $table->timestamp('printed_at')->nullable();
            }

            if (!Schema::hasColumn('question_papers', 'printed_by')) {
                $table->foreignId('printed_by')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();
            }

            if (!Schema::hasColumn('question_papers', 'archived_at')) {
                $table->timestamp('archived_at')->nullable();
            }

            if (!Schema::hasColumn('question_papers', 'archived_by')) {
                $table->foreignId('archived_by')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();
            }
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("
                ALTER TABLE question_papers
                MODIFY status ENUM(
                    'draft',
                    'approved',
                    'published',
                    'finalized',
                    'printed',
                    'archived'
                ) DEFAULT 'draft'
            ");
        }
    }

    public function down(): void
    {
        Schema::table('question_papers', function (Blueprint $table) {

            $table->dropForeign(['finalized_by']);
            $table->dropForeign(['printed_by']);
            $table->dropForeign(['archived_by']);

            $table->dropColumn([
                'instructions',
                'exam_type',
                'is_published',
                'finalized_at',
                'finalized_by',
                'printed_at',
                'printed_by',
                'archived_at',
                'archived_by',
            ]);
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("
                ALTER TABLE question_papers
                MODIFY status ENUM(
                    'draft',
                    'approved',
                    'published'
                ) DEFAULT 'draft'
            ");
        }
    }
};