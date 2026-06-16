<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('subscription_plan_id')
                ->nullable()
                ->constrained('subscription_plans')
                ->nullOnDelete();

            $table->foreignId('demo_enquiry_id')
                ->nullable()
                ->constrained('demo_enquiries')
                ->nullOnDelete();

            $table->string('school_name');
            $table->string('contact_person')->nullable();
            $table->string('mobile')->nullable();
            $table->string('email')->nullable();

            $table->enum('status', [
                'trial',
                'active',
                'expired',
                'cancelled',
                'pending_payment'
            ])->default('pending_payment');

            $table->decimal('amount', 10, 2)->default(0);
            $table->date('starts_at')->nullable();
            $table->date('ends_at')->nullable();

            $table->boolean('is_trial')->default(false);
            $table->boolean('auto_renew')->default(false);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};