<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('timetable_rooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('code', 50)->nullable();
            $table->enum('room_type', ['classroom', 'laboratory', 'computer_lab', 'library', 'activity', 'other'])
                ->default('classroom');
            $table->unsignedInteger('capacity')->nullable();
            $table->json('supported_subject_ids')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['subscription_id', 'name'], 'tt_rooms_subscription_name_unique');
            $table->unique(['subscription_id', 'code'], 'tt_rooms_subscription_code_unique');
            $table->index(['subscription_id', 'room_type', 'is_active'], 'tt_rooms_scope_index');
        });

        Schema::table('parallel_groups', function (Blueprint $table) {
            $table->foreignId('subscription_id')
                ->nullable()
                ->after('id')
                ->constrained()
                ->cascadeOnDelete();
        });

        DB::table('parallel_groups')
            ->join('grades', 'grades.id', '=', 'parallel_groups.grade_id')
            ->whereNull('parallel_groups.subscription_id')
            ->update([
                'parallel_groups.subscription_id' => DB::raw('grades.subscription_id'),
            ]);

        Schema::table('parallel_groups', function (Blueprint $table) {
            $table->index(['subscription_id', 'grade_id', 'is_active'], 'parallel_groups_tenant_grade_index');
        });

        Schema::table('timetable_entries', function (Blueprint $table) {
            $table->foreignId('room_id')
                ->nullable()
                ->after('student_group_name')
                ->constrained('timetable_rooms')
                ->nullOnDelete();
            $table->boolean('is_locked')->default(false)->after('remarks');
            $table->index(['room_id', 'weekday', 'school_bell_id', 'is_active'], 'tt_entries_room_slot_index');
            $table->index(['weekly_timetable_id', 'is_locked'], 'tt_entries_locked_index');
        });
    }

    public function down(): void
    {
        Schema::table('timetable_entries', function (Blueprint $table) {
            $table->dropIndex('tt_entries_room_slot_index');
            $table->dropIndex('tt_entries_locked_index');
            $table->dropConstrainedForeignId('room_id');
            $table->dropColumn('is_locked');
        });

        Schema::table('parallel_groups', function (Blueprint $table) {
            $table->dropIndex('parallel_groups_tenant_grade_index');
            $table->dropConstrainedForeignId('subscription_id');
        });

        Schema::dropIfExists('timetable_rooms');
    }
};