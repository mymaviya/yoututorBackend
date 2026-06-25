<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->addSubscriptionId('proposals');
        $this->addSubscriptionId('proposal_versions');
        $this->addSubscriptionId('quotations');
        $this->addSubscriptionId('invoices');
        $this->addSubscriptionId('invoice_payments');
    }

    public function down(): void
    {
        $this->dropSubscriptionId('invoice_payments');
        $this->dropSubscriptionId('invoices');
        $this->dropSubscriptionId('quotations');
        $this->dropSubscriptionId('proposal_versions');
        $this->dropSubscriptionId('proposals');
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
