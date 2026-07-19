<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('timetable_rules', function (Blueprint $table) {
            if (! Schema::hasColumn('timetable_rules', 'constraint_type')) {
                $table->enum('constraint_type', ['hard', 'soft'])
                    ->default('soft')
                    ->after('value_type');
            }

            if (! Schema::hasColumn('timetable_rules', 'priority')) {
                $table->unsignedTinyInteger('priority')
                    ->default(5)
                    ->after('constraint_type');
            }

            if (! Schema::hasColumn('timetable_rules', 'effective_from')) {
                $table->date('effective_from')
                    ->nullable()
                    ->after('description');
            }

            if (! Schema::hasColumn('timetable_rules', 'effective_to')) {
                $table->date('effective_to')
                    ->nullable()
                    ->after('effective_from');
            }
        });

        Schema::table('timetable_rules', function (Blueprint $table) {
            $table->index(
                ['subscription_id', 'academic_year_id', 'is_active'],
                'tt_rules_subscription_year_active_index'
            );
            $table->index(
                ['subscription_id', 'constraint_type', 'priority'],
                'tt_rules_constraint_priority_index'
            );
        });
    }

    public function down(): void
    {
        Schema::table('timetable_rules', function (Blueprint $table) {
            $table->dropIndex('tt_rules_subscription_year_active_index');
            $table->dropIndex('tt_rules_constraint_priority_index');

            $columns = collect([
                'constraint_type',
                'priority',
                'effective_from',
                'effective_to',
            ])->filter(
                fn (string $column) => Schema::hasColumn('timetable_rules', $column)
            )->all();

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
