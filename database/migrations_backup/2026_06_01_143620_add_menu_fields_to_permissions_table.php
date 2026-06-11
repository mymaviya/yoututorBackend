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
        Schema::table('permissions', function (Blueprint $table) {
            $table->boolean('show_in_sidebar')->default(false);
            $table->string('menu_title')->nullable();
            $table->string('menu_icon')->nullable();
            $table->string('menu_route_name')->nullable();
            $table->string('menu_group')->nullable();
            $table->integer('menu_order')->default(0);

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('permissions', function (Blueprint $table) {
            //
        });
    }
};
