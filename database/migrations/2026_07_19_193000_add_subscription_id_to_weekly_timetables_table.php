<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('weekly_timetables', function (Blueprint $table) {
            if (! Schema::hasColumn('weekly_timetables', 'subscription_id')) {
                $table->foreignId('subscription_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('subscriptions')
                    ->cascadeOnDelete();
            }
        });

        DB::table('weekly_timetables')
            ->whereNull('subscription_id')
            ->orderBy('id')
            ->chunkById(500, function ($rows) {
                foreach ($rows as $row) {
                    $subscriptionId = DB::table('timetable_templates')
                        ->where('id', $row->timetable_template_id)
                        ->value('subscription_id');

                    if ($subscriptionId !== null) {
                        DB::table('weekly_timetables')
                            ->where('id', $row->id)
                            ->update(['subscription_id' => $subscriptionId]);
                    }
                }
            });

        Schema::table('weekly_timetables', function (Blueprint $table) {
            $table->index(
                ['subscription_id', 'academic_year_id', 'grade_id', 'section_id', 'stream_id', 'is_active'],
                'weekly_timetables_tenant_scope_index'
            );
        });
    }

    public function down(): void
    {
        Schema::table('weekly_timetables', function (Blueprint $table) {
            $table->dropIndex('weekly_timetables_tenant_scope_index');

            if (Schema::hasColumn('weekly_timetables', 'subscription_id')) {
                $table->dropConstrainedForeignId('subscription_id');
            }
        });
    }
};
