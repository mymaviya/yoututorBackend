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
        Schema::table('users', function (Blueprint $table) {

            $table->integer('session_timeout_minutes')->default(30);
            $table->boolean('allow_multiple_sessions')->default(false);
            $table->json('allowed_ips')->nullable();
            $table->boolean('device_lock_enabled')->default(false);
            $table->integer('max_devices')->default(1);
            $table->boolean('otp_login_enabled')->default(false);
            $table->boolean('holiday_login_allowed')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            //
        });
    }
};
