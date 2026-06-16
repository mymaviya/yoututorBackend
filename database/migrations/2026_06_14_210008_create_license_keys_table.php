<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('license_keys', function (Blueprint $table) {
            $table->id();

            $table->foreignId('subscription_id')
                ->constrained('subscriptions')
                ->cascadeOnDelete();

            $table->string('license_key')->unique();
            $table->string('domain')->nullable();
            $table->string('status')->default('active');

            $table->timestamp('activated_at')->nullable();
            $table->timestamp('expires_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('license_keys');
    }
};