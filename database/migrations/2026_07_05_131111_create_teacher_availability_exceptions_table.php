<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teacher_availability_exceptions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('subscription_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('academic_year_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            $table->foreignId('teacher_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->date('exception_date')->nullable();

            $table->string('weekday', 20)->nullable();

            $table->foreignId('school_bell_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            $table->enum('status', [
                'busy',
                'leave',
                'meeting',
                'training',
                'exam_duty',
                'assembly',
                'blocked',
                'extra_class',
            ])->default('busy');

            $table->string('reason')->nullable();
            $table->text('remarks')->nullable();

            $table->boolean('is_full_day')->default(false);
            $table->boolean('is_recurring')->default(false);

            $table->enum('recurrence_type', [
                'weekly',
                'monthly',
            ])->nullable();

            $table->date('valid_from')->nullable();
            $table->date('valid_to')->nullable();

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->index(
                ['subscription_id', 'teacher_id', 'exception_date'],
                'idx_tae_teacher_date'
            );

            $table->index(
                ['subscription_id', 'weekday', 'school_bell_id'],
                'idx_tae_day_bell'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teacher_availability_exceptions');
    }
};