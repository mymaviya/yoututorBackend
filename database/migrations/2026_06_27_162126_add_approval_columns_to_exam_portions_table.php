<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exam_portions', function (Blueprint $table) {

            if (!Schema::hasColumn('exam_portions', 'status')) {
                $table->string('status')
                    ->default('draft')
                    ->after('exam_name_id');
            }

            if (!Schema::hasColumn('exam_portions', 'submitted_at')) {
                $table->timestamp('submitted_at')
                    ->nullable()
                    ->after('status');
            }

            if (!Schema::hasColumn('exam_portions', 'approved_by')) {
                $table->unsignedBigInteger('approved_by')
                    ->nullable()
                    ->after('submitted_at');
            }

            if (!Schema::hasColumn('exam_portions', 'approved_at')) {
                $table->timestamp('approved_at')
                    ->nullable()
                    ->after('approved_by');
            }

            if (!Schema::hasColumn('exam_portions', 'rejection_reason')) {
                $table->text('rejection_reason')
                    ->nullable()
                    ->after('approved_at');
            }

            if (Schema::hasColumn('exam_portions', 'approved_by')) {
                $table->foreign('approved_by', 'exam_portions_approved_by_fk')
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('exam_portions', function (Blueprint $table) {
            if (Schema::hasColumn('exam_portions', 'approved_by')) {
                $table->dropForeign('exam_portions_approved_by_fk');
            }

            $table->dropColumn([
                'status',
                'submitted_at',
                'approved_by',
                'approved_at',
                'rejection_reason',
            ]);
        });
    }
};
