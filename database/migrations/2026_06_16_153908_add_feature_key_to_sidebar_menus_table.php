<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sidebar_menus', function (Blueprint $table) {
            if (!Schema::hasColumn('sidebar_menus', 'feature_key')) {
                $table->string('feature_key', 100)
                    ->nullable()
                    ->after('permission_slug')
                    ->index('sidebar_feature_key_idx');
            }
        });
    }

    public function down(): void
    {
        Schema::table('sidebar_menus', function (Blueprint $table) {
            if (Schema::hasColumn('sidebar_menus', 'feature_key')) {
                $table->dropIndex('sidebar_feature_key_idx');
                $table->dropColumn('feature_key');
            }
        });
    }
};