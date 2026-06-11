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
        Schema::create('users', function (Blueprint $table) {
            $table->id();

            $table->foreignId('role_id')
                ->nullable()
                ->constrained('roles')
                ->nullOnDelete();

            $table->string('name');
            $table->string('email')->unique();
            $table->string('contact')->nullable();
            $table->text('address')->nullable();
            $table->string('profile')->nullable();
            $table->string('password');

            $table->string('role')->default('user');

            $table->boolean('is_active')->default(true);
            $table->boolean('login_enabled')->default(true);

            $table->date('login_start_date')->nullable();
            $table->date('login_end_date')->nullable();

            $table->time('daily_login_start_time')->nullable();
            $table->time('daily_login_end_time')->nullable();

            $table->integer('session_timeout_minutes')->default(30);
            $table->boolean('allow_multiple_sessions')->default(false);
            $table->string('current_session_id')->nullable();
            $table->timestamp('last_activity_at')->nullable();

            $table->boolean('password_change_required')->default(false);

            $table->rememberToken();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
