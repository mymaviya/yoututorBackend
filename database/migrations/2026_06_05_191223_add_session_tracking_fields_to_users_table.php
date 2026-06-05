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

            $table->string('current_session_id')
                ->nullable()
                ->after('allow_multiple_sessions');

            $table->timestamp('last_activity_at')
                ->nullable()
                ->after('current_session_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {

            $table->dropColumn([
                'current_session_id',
                'last_activity_at',
            ]);
        });
    }
};
