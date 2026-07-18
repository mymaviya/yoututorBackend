<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teacher_substitutions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('timetable_entry_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('original_teacher_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->foreignId('substitute_teacher_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->date('substitution_date');

            $table->string('reason')->nullable();
            $table->text('remarks')->nullable();

            $table->enum('status', [
                'pending',
                'approved',
                'rejected',
                'completed',
            ])->default('pending');

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();

            $table->unique(
                ['timetable_entry_id', 'substitution_date'],
                'uniq_sub_entry_date'
            );

            $table->index(
                ['substitute_teacher_id', 'substitution_date'],
                'idx_sub_teacher_date'
            );

            $table->index(
                ['original_teacher_id', 'substitution_date'],
                'idx_org_teacher_date'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teacher_substitutions');
    }
};