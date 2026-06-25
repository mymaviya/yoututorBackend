<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->addSubscriptionId('subjects');
        $this->addSubscriptionId('lessons');
        $this->addSubscriptionId('question_type_assignments');
        $this->addSubscriptionId('teacher_assignments');
        $this->addSubscriptionId('teacher_grades');
    }

    public function down(): void
    {
        $this->dropSubscriptionId('teacher_grades');
        $this->dropSubscriptionId('teacher_assignments');
        $this->dropSubscriptionId('question_type_assignments');
        $this->dropSubscriptionId('lessons');
        $this->dropSubscriptionId('subjects');
    }

    private function addSubscriptionId(string $tableName): void
    {
        if (! Schema::hasTable($tableName) || Schema::hasColumn($tableName, 'subscription_id')) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) {
            $table->foreignId('subscription_id')
                ->nullable()
                ->after('id')
                ->constrained('subscriptions')
                ->nullOnDelete();
        });
    }

    private function dropSubscriptionId(string $tableName): void
    {
        if (! Schema::hasTable($tableName) || ! Schema::hasColumn($tableName, 'subscription_id')) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) {
            $table->dropConstrainedForeignId('subscription_id');
        });
    }
};
