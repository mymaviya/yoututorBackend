<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->addSubscriptionId('audit_logs');
        $this->addSubscriptionId('app_notifications');
        $this->addSubscriptionId('user_devices');
    }

    public function down(): void
    {
        $this->dropSubscriptionId('user_devices');
        $this->dropSubscriptionId('app_notifications');
        $this->dropSubscriptionId('audit_logs');
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
