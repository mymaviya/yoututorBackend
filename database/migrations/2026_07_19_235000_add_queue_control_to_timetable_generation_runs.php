<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('timetable_generation_runs')) {
            return;
        }

        Schema::table('timetable_generation_runs', function (Blueprint $table) {
            if (! Schema::hasColumn('timetable_generation_runs', 'queue_job_id')) {
                $table->string('queue_job_id', 100)->nullable()->after('parent_run_id');
            }

            if (! Schema::hasColumn('timetable_generation_runs', 'attempt_count')) {
                $table->unsignedSmallInteger('attempt_count')->default(0)->after('queue_job_id');
            }

            if (! Schema::hasColumn('timetable_generation_runs', 'cancellation_requested_at')) {
                $table->timestamp('cancellation_requested_at')->nullable()->after('error_message');
            }

            if (! Schema::hasColumn('timetable_generation_runs', 'cancelled_at')) {
                $table->timestamp('cancelled_at')->nullable()->after('cancellation_requested_at');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('timetable_generation_runs')) {
            return;
        }

        Schema::table('timetable_generation_runs', function (Blueprint $table) {
            foreach ([
                'cancelled_at',
                'cancellation_requested_at',
                'attempt_count',
                'queue_job_id',
            ] as $column) {
                if (Schema::hasColumn('timetable_generation_runs', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
