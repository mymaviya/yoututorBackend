<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('audit_logs')) {
            Schema::table('audit_logs', function (Blueprint $table) {
                if (! Schema::hasColumn('audit_logs', 'description')) {
                    $table->text('description')->nullable()->after('action');
                }

                if (! Schema::hasColumn('audit_logs', 'browser')) {
                    $table->string('browser')->nullable()->after('ip_address');
                }

                if (! Schema::hasColumn('audit_logs', 'platform')) {
                    $table->string('platform')->nullable()->after('browser');
                }
            });
        }

        if (Schema::hasTable('user_devices')) {
            Schema::table('user_devices', function (Blueprint $table) {
                if (! Schema::hasColumn('user_devices', 'browser')) {
                    $table->string('browser')->nullable()->after('device_name');
                }

                if (! Schema::hasColumn('user_devices', 'platform')) {
                    $table->string('platform')->nullable()->after('browser');
                }

                if (! Schema::hasColumn('user_devices', 'last_used_at')) {
                    $table->timestamp('last_used_at')->nullable()->after('last_seen_at');
                }

                if (! Schema::hasColumn('user_devices', 'is_trusted')) {
                    $table->boolean('is_trusted')->default(false)->after('last_used_at');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('user_devices')) {
            Schema::table('user_devices', function (Blueprint $table) {
                foreach (['is_trusted', 'last_used_at', 'platform', 'browser'] as $column) {
                    if (Schema::hasColumn('user_devices', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('audit_logs')) {
            Schema::table('audit_logs', function (Blueprint $table) {
                foreach (['platform', 'browser', 'description'] as $column) {
                    if (Schema::hasColumn('audit_logs', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
