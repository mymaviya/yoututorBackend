<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('demo_enquiries', function (Blueprint $table) {
            if (!Schema::hasColumn('demo_enquiries', 'follow_up_date')) {
                $table->dateTime('follow_up_date')->nullable()->after('demo_ends_at');
            }

            if (!Schema::hasColumn('demo_enquiries', 'last_contact_at')) {
                $table->dateTime('last_contact_at')->nullable()->after('follow_up_date');
            }

            if (!Schema::hasColumn('demo_enquiries', 'assigned_to')) {
                $table->foreignId('assigned_to')
                    ->nullable()
                    ->after('last_contact_at')
                    ->constrained('users')
                    ->nullOnDelete();
            }

            if (!Schema::hasColumn('demo_enquiries', 'converted_subscription_id')) {
                $table->foreignId('converted_subscription_id')
                    ->nullable()
                    ->after('assigned_to')
                    ->constrained('subscriptions')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('demo_enquiries', function (Blueprint $table) {
            if (Schema::hasColumn('demo_enquiries', 'converted_subscription_id')) {
                $table->dropConstrainedForeignId('converted_subscription_id');
            }

            if (Schema::hasColumn('demo_enquiries', 'assigned_to')) {
                $table->dropConstrainedForeignId('assigned_to');
            }

            if (Schema::hasColumn('demo_enquiries', 'last_contact_at')) {
                $table->dropColumn('last_contact_at');
            }

            if (Schema::hasColumn('demo_enquiries', 'follow_up_date')) {
                $table->dropColumn('follow_up_date');
            }
        });
    }
};