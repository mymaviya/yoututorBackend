<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('weekly_timetables')) {
            return;
        }

        Schema::table('weekly_timetables', function (Blueprint $table) {
            if (! Schema::hasColumn('weekly_timetables', 'status')) {
                $table->string('status', 20)->default('draft')->after('is_generated');
            }
            if (! Schema::hasColumn('weekly_timetables', 'version')) {
                $table->unsignedInteger('version')->default(1)->after('status');
            }
            if (! Schema::hasColumn('weekly_timetables', 'published_at')) {
                $table->timestamp('published_at')->nullable()->after('version');
            }
            if (! Schema::hasColumn('weekly_timetables', 'published_by')) {
                $table->foreignId('published_by')->nullable()->after('published_at')
                    ->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('weekly_timetables', 'archived_at')) {
                $table->timestamp('archived_at')->nullable()->after('published_by');
            }
        });

        DB::table('weekly_timetables')
            ->where('is_generated', true)
            ->where('is_active', true)
            ->where(function ($query) {
                $query->whereNull('status')->orWhere('status', 'draft');
            })
            ->update([
                'status' => 'published',
                'published_at' => DB::raw('COALESCE(published_at, updated_at, created_at)'),
            ]);

        try {
            Schema::table('weekly_timetables', function (Blueprint $table) {
                $table->index(
                    ['subscription_id', 'status', 'academic_year_id'],
                    'weekly_timetables_lifecycle_index'
                );
            });
        } catch (Throwable) {
            // Index may already exist after a partially completed migration.
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('weekly_timetables')) {
            return;
        }

        try {
            Schema::table('weekly_timetables', function (Blueprint $table) {
                $table->dropIndex('weekly_timetables_lifecycle_index');
            });
        } catch (Throwable) {
            // Ignore missing index during rollback of a partial migration.
        }

        Schema::table('weekly_timetables', function (Blueprint $table) {
            if (Schema::hasColumn('weekly_timetables', 'published_by')) {
                $table->dropConstrainedForeignId('published_by');
            }
            foreach (['archived_at', 'published_at', 'version', 'status'] as $column) {
                if (Schema::hasColumn('weekly_timetables', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
