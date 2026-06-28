<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_paper_generations', function (Blueprint $table) {
            if (! Schema::hasColumn('ai_paper_generations', 'progress_percentage')) {
                $table->unsignedTinyInteger('progress_percentage')->default(0)->after('status');
            }

            if (! Schema::hasColumn('ai_paper_generations', 'current_section')) {
                $table->string('current_section')->nullable()->after('progress_percentage');
            }

            if (! Schema::hasColumn('ai_paper_generations', 'progress_message')) {
                $table->string('progress_message')->nullable()->after('current_section');
            }

            if (! Schema::hasColumn('ai_paper_generations', 'started_at')) {
                $table->timestamp('started_at')->nullable()->after('progress_message');
            }

            if (! Schema::hasColumn('ai_paper_generations', 'completed_at')) {
                $table->timestamp('completed_at')->nullable()->after('started_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('ai_paper_generations', function (Blueprint $table) {
            $table->dropColumn([
                'progress_percentage',
                'current_section',
                'progress_message',
                'started_at',
                'completed_at',
            ]);
        });
    }
};