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
                $table->foreignId('subscription_id')->constrained()->cascadeOnDelete();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('parent_run_id')->nullable()->constrained('timetable_generation_runs')->nullOnDelete();
                $table->enum('mode', ['single', 'batch'])->default('single');
                $table->boolean('is_preview')->default(false);
                $table->enum('status', ['queued', 'running', 'completed', 'partial', 'failed'])->default('queued');
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

                $table->index(
                    ['subscription_id', 'status', 'created_at'],
                    'tt_generation_runs_scope_index'
                );
            });
        }

        if (! Schema::hasTable('timetable_generation_conflicts')) {
            Schema::create('timetable_generation_conflicts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('timetable_generation_run_id')
                    ->constrained('timetable_generation_runs')
                    ->cascadeOnDelete();
                $table->unsignedInteger('item_index')->nullable();
                $table->string('conflict_type', 80)->default('generation');
                $table->enum('severity', ['warning', 'error'])->default('warning');
                $table->foreignId('grade_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('section_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('stream_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('teacher_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('subject_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('school_bell_id')->nullable()->constrained()->nullOnDelete();
                $table->unsignedTinyInteger('weekday')->nullable();
                $table->text('message');
                $table->json('context')->nullable();
                $table->timestamps();

                $table->index(
                    ['timetable_generation_run_id', 'severity'],
                    'tt_generation_conflicts_run_index'
                );
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('timetable_generation_conflicts');
        Schema::dropIfExists('timetable_generation_runs');
    }
};