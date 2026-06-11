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
        Schema::create('sidebar_menus', function (Blueprint $table) {
            $table->id();

            $table->foreignId('parent_id')
                ->nullable()
                ->constrained('sidebar_menus')
                ->nullOnDelete();

            $table->string('title');
            $table->string('icon')->nullable();
            $table->string('route')->nullable();

            $table->string('route_name')->nullable();
            $table->string('group_name')->nullable();
            $table->string('parent_menu')->nullable();

            $table->string('permission_slug')->nullable();
            $table->string('role_slug')->nullable();

            $table->string('badge')->nullable();
            $table->string('badge_color')->nullable();

            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('show_in_sidebar')->default(true);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sidebar_menus');
    }
};
