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
        Schema::create('subscription_question_bank_purchases', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('subscription_id');
            $table->unsignedBigInteger('question_bank_package_id');
            $table->unsignedBigInteger('created_by')->nullable();

            $table->decimal('amount', 10, 2)->default(0);

            $table->enum('status', [
                'pending',
                'active',
                'expired',
                'cancelled',
            ])->default('active');

            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();

            $table->timestamps();

            $table->foreign(
                'subscription_id',
                'sqbp_subscription_fk'
            )->references('id')
                ->on('subscriptions')
                ->cascadeOnDelete();

            $table->foreign(
                'question_bank_package_id',
                'sqbp_package_fk'
            )->references('id')
                ->on('question_bank_packages')
                ->cascadeOnDelete();

            $table->foreign(
                'created_by',
                'sqbp_created_by_fk'
            )->references('id')
                ->on('users')
                ->nullOnDelete();

            $table->unique([
                'subscription_id',
                'question_bank_package_id',
            ], 'sqbp_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscription_question_bank_purchases');
    }
};
