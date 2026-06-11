<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {

            $table->boolean('login_enabled')->default(true)->after('is_active');

            $table->date('login_start_date')->default(DB::raw('CURRENT_DATE'));
            $table->date('login_end_date')->nullable();

            $table->time('daily_login_start_time')->nullable();
            $table->time('daily_login_end_time')->nullable();

        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {

            $table->dropColumn([
                'login_enabled',
                'login_start_date',
                'login_end_date',
                'daily_login_start_time',
                'daily_login_end_time',
            ]);
        });
    }
};
