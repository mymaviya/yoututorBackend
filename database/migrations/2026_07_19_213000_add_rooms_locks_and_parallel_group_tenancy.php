<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('timetable_rooms')) {
            Schema::create('timetable_rooms', function (Blueprint $table) {
                $table->id();
                $table->foreignId('subscription_id')->constrained()->cascadeOnDelete();
                $table->string('name', 100);
                $table->string('code', 50)->nullable();
                $table->enum('room_type', [
                    'classroom',
                    'laboratory',
                    'computer_lab',
                    'library',
                    'activity',
                    'other',
                ])->default('classroom');
                $table->unsignedInteger('capacity')->nullable();
                $table->json('supported_subject_ids')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->unique(['subscription_id', 'name'], 'tt_rooms_subscription_name_unique');
                $table->unique(['subscription_id', 'code'], 'tt_rooms_subscription_code_unique');
                $table->index(['subscription_id', 'room_type', 'is_active'], 'tt_rooms_scope_index');
            });
        }

        if (Schema::hasTable('parallel_groups')) {
            if (! Schema::hasColumn('parallel_groups', 'subscription_id')) {
                Schema::table('parallel_groups', function (Blueprint $table) {
                    $table->foreignId('subscription_id')
                        ->nullable()
                        ->after('id')
                        ->constrained()
                        ->cascadeOnDelete();
                });
            }

            /*
             * Grades are global master records and do not contain subscription_id.
             * Resolve each parallel group's tenant through its subject items instead:
             * parallel_group_items.subject_id -> subjects.subscription_id.
             *
             * The grouped subquery prevents duplicate-update ambiguity when a group
             * contains multiple subject items.
             */
            if (Schema::hasTable('parallel_group_items') && Schema::hasTable('subjects')) {
                DB::statement(<<<'SQL'
                    UPDATE parallel_groups AS pg
                    INNER JOIN (
                        SELECT
                            pgi.parallel_group_id,
                            MIN(s.subscription_id) AS subscription_id
                        FROM parallel_group_items AS pgi
                        INNER JOIN subjects AS s ON s.id = pgi.subject_id
                        WHERE s.subscription_id IS NOT NULL
                        GROUP BY pgi.parallel_group_id
                    ) AS tenant_source
                        ON tenant_source.parallel_group_id = pg.id
                    SET pg.subscription_id = tenant_source.subscription_id
                    WHERE pg.subscription_id IS NULL
                SQL);
            }

            if (! $this->indexExists('parallel_groups', 'parallel_groups_tenant_grade_index')) {
                Schema::table('parallel_groups', function (Blueprint $table) {
                    $table->index(
                        ['subscription_id', 'grade_id', 'is_active'],
                        'parallel_groups_tenant_grade_index'
                    );
                });
            }
        }

        if (Schema::hasTable('timetable_entries')) {
            if (! Schema::hasColumn('timetable_entries', 'room_id')) {
                Schema::table('timetable_entries', function (Blueprint $table) {
                    $table->foreignId('room_id')
                        ->nullable()
                        ->after('student_group_name')
                        ->constrained('timetable_rooms')
                        ->nullOnDelete();
                    $table->index(
                        ['room_id', 'weekday', 'school_bell_id', 'is_active'],
                        'tt_entries_room_slot_index'
                    );
                });
            }

            if (! Schema::hasColumn('timetable_entries', 'is_locked')) {
                Schema::table('timetable_entries', function (Blueprint $table) {
                    $table->boolean('is_locked')->default(false)->after('remarks');
                    $table->index(
                        ['weekly_timetable_id', 'is_locked'],
                        'tt_entries_locked_index'
                    );
                });
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('timetable_entries')) {
            if (Schema::hasColumn('timetable_entries', 'room_id')) {
                Schema::table('timetable_entries', function (Blueprint $table) {
                    if ($this->indexExists('timetable_entries', 'tt_entries_room_slot_index')) {
                        $table->dropIndex('tt_entries_room_slot_index');
                    }
                    $table->dropConstrainedForeignId('room_id');
                });
            }

            if (Schema::hasColumn('timetable_entries', 'is_locked')) {
                Schema::table('timetable_entries', function (Blueprint $table) {
                    if ($this->indexExists('timetable_entries', 'tt_entries_locked_index')) {
                        $table->dropIndex('tt_entries_locked_index');
                    }
                    $table->dropColumn('is_locked');
                });
            }
        }

        if (Schema::hasTable('parallel_groups')
            && Schema::hasColumn('parallel_groups', 'subscription_id')) {
            Schema::table('parallel_groups', function (Blueprint $table) {
                if ($this->indexExists('parallel_groups', 'parallel_groups_tenant_grade_index')) {
                    $table->dropIndex('parallel_groups_tenant_grade_index');
                }
                $table->dropConstrainedForeignId('subscription_id');
            });
        }

        Schema::dropIfExists('timetable_rooms');
    }

    private function indexExists(string $table, string $index): bool
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return false;
        }

        return DB::table('information_schema.statistics')
            ->where('table_schema', DB::connection()->getDatabaseName())
            ->where('table_name', $table)
            ->where('index_name', $index)
            ->exists();
    }
};