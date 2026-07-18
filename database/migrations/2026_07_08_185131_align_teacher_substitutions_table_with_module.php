<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teacher_substitutions', function (Blueprint $table) {

            $table->foreignId('absent_teacher_id')
                ->nullable()
                ->after('timetable_entry_id')
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('assigned_by')
                ->nullable()
                ->after('status')
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('approved_by')
                ->nullable()
                ->after('assigned_by')
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('approved_at')->nullable();

            $table->boolean('is_active')->default(true);

            $table->foreignId('school_bell_id')
                ->nullable()
                ->constrained('school_bells')
                ->nullOnDelete();
        });

        /*
        |--------------------------------------------------------------------------
        | Migrate Existing Data
        |--------------------------------------------------------------------------
        */

        DB::table('teacher_substitutions')
            ->whereNull('absent_teacher_id')
            ->update([
                'absent_teacher_id' => DB::raw('original_teacher_id'),
                'assigned_by' => DB::raw('created_by'),
            ]);
    }

    public function down(): void
    {
        Schema::table('teacher_substitutions', function (Blueprint $table) {
            $table->dropColumn([
                'approved_at',
                'is_active',
            ]);

            $table->dropConstrainedForeignId('absent_teacher_id');
            $table->dropConstrainedForeignId('assigned_by');
            $table->dropConstrainedForeignId('approved_by');
            $table->dropConstrainedForeignId('school_bell_id');
        });
    }
};
