<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('timetable_generation_runs')) {
            Schema::create('timetable_generation_runs', function (Blueprint $table) {
                $table->id();

                $table->unsignedBigInteger('subscription_id');
                $table->unsignedBigInteger('user_id')->nullable();
                $table->unsignedBigInteger('parent_run_id')->nullable();

                $table->enum('mode', ['single', 'batch'])->default('single');
                $table->boolean('is_preview')->default(false);
                $table->enum(
                    'status',
                    ['queued', 'running', 'completed', 'partial', 'failed']
                )->default('queued');
                $table->unsignedTinyInteger('progress_percentage')->default(0);
                $table->unsignedInteger('requested_items')->default(1);
                $table->unsignedInteger('processed_items')->default(0);
                $table->unsignedInteger('successful_items')->default(0);
                $table->unsignedInteger('failed_items')->default(0);
                $table->json('request_payload');
                $table->json('result_summary')->nullable();
                $table->text('error_message')->nullable();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();

                $table->foreign('subscription_id', 'tt_gen_runs_sub_fk')
                    ->references('id')
                    ->on('subscriptions')
                    ->cascadeOnDelete();

                $table->foreign('user_id', 'tt_gen_runs_user_fk')
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();

                $table->foreign('parent_run_id', 'tt_gen_runs_parent_fk')
                    ->references('id')
                    ->on('timetable_generation_runs')
                    ->nullOnDelete();

                $table->index(
                    ['subscription_id', 'status', 'created_at'],
                    'tt_generation_runs_scope_index'
                );
            });
        }

        /*
         * A failed MySQL CREATE TABLE may leave this new table behind without
         * all constraints. Because this migration has not been recorded yet,
         * it is safe to rebuild the conflict table before retrying.
         */
        if (Schema::hasTable('timetable_generation_conflicts')) {
            Schema::drop('timetable_generation_conflicts');
        }

        Schema::create('timetable_generation_conflicts', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('timetable_generation_run_id');
            $table->unsignedInteger('item_index')->nullable();
            $table->string('conflict_type', 80)->default('generation');
            $table->enum('severity', ['warning', 'error'])->default('warning');

            $table->unsignedBigInteger('grade_id')->nullable();
            $table->unsignedBigInteger('section_id')->nullable();
            $table->unsignedBigInteger('stream_id')->nullable();
            $table->unsignedBigInteger('teacher_id')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->unsignedBigInteger('school_bell_id')->nullable();

            $table->unsignedTinyInteger('weekday')->nullable();
            $table->text('message');
            $table->json('context')->nullable();
            $table->timestamps();

            $table->foreign('timetable_generation_run_id', 'tt_gen_conf_run_fk')
                ->references('id')
                ->on('timetable_generation_runs')
                ->cascadeOnDelete();

            $table->foreign('grade_id', 'tt_gen_conf_grade_fk')
                ->references('id')
                ->on('grades')
                ->nullOnDelete();

            $table->foreign('section_id', 'tt_gen_conf_section_fk')
                ->references('id')
                ->on('sections')
                ->nullOnDelete();

            $table->foreign('stream_id', 'tt_gen_conf_stream_fk')
                ->references('id')
                ->on('streams')
                ->nullOnDelete();

            $table->foreign('teacher_id', 'tt_gen_conf_teacher_fk')
                ->references('id')
                ->on('users')
                ->nullOnDelete();

            $table->foreign('subject_id', 'tt_gen_conf_subject_fk')
                ->references('id')
                ->on('subjects')
                ->nullOnDelete();

            $table->foreign('school_bell_id', 'tt_gen_conf_bell_fk')
                ->references('id')
                ->on('school_bells')
                ->nullOnDelete();

            $table->index(
                ['timetable_generation_run_id', 'severity'],
                'tt_generation_conflicts_run_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('timetable_generation_conflicts');
        Schema::dropIfExists('timetable_generation_runs');
    }
};
