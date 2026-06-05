<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('sidebar_menus', function (Blueprint $table) {
            $table->string('parent_menu')->nullable()->after('group_name');
            $table->string('badge')->nullable()->after('permission_slug');
            $table->string('badge_color')->nullable()->after('badge');
            $table->boolean('show_in_sidebar')->default(true)->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('sidebar_menus', function (Blueprint $table) {
            $table->dropColumn([
                'parent_menu',
                'badge',
                'badge_color',
                'show_in_sidebar',
            ]);
        });
    }
};
