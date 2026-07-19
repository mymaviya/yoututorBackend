<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bell_schedule_settings', function (Blueprint $table): void {
            $table->foreignId('subscription_id')
                ->nullable()
                ->after('id')
                ->constrained('subscriptions')
                ->cascadeOnDelete();

            $table->index(
                ['subscription_id', 'is_active'],
                'bell_settings_subscription_active_index'
            );
        });

        Schema::table('school_bells', function (Blueprint $table): void {
            $table->foreignId('subscription_id')
                ->nullable()
                ->after('id')
                ->constrained('subscriptions')
                ->cascadeOnDelete();

            $table->index(
                ['subscription_id', 'is_active', 'sort_order'],
                'school_bells_subscription_active_sort_index'
            );
        });
    }

    public function down(): void
    {
        Schema::table('school_bells', function (Blueprint $table): void {
            $table->dropIndex('school_bells_subscription_active_sort_index');
            $table->dropConstrainedForeignId('subscription_id');
        });

        Schema::table('bell_schedule_settings', function (Blueprint $table): void {
            $table->dropIndex('bell_settings_subscription_active_index');
            $table->dropConstrainedForeignId('subscription_id');
        });
    }
};
