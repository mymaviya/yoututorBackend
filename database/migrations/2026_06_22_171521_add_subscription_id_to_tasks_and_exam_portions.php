<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teacher_question_tasks', function (Blueprint $table) {
            $table->foreignId('subscription_id')
                ->nullable()
                ->after('id')
                ->constrained('subscriptions')
                ->nullOnDelete();
        });

        Schema::table('exam_portions', function (Blueprint $table) {
            $table->foreignId('subscription_id')
                ->nullable()
                ->after('id')
                ->constrained('subscriptions')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('teacher_question_tasks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('subscription_id');
        });

        Schema::table('exam_portions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('subscription_id');
        });
    }
};
