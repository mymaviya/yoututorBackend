<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_renewals', function (Blueprint $table) {
            $table->id();

            $table->foreignId('subscription_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('subscription_plan_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            $table->foreignId('payment_transaction_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            $table->date('old_start_date')->nullable();
            $table->date('old_end_date')->nullable();

            $table->date('new_start_date');
            $table->date('new_end_date');

            $table->integer('duration_days')->default(365);

            $table->decimal('old_amount', 12, 2)->default(0);
            $table->decimal('renewal_amount', 12, 2)->default(0);

            $table->enum('renewal_type', [
                'renewal',
                'upgrade',
                'downgrade',
                'trial_conversion',
                'manual_extension'
            ])->default('renewal');

            $table->text('remarks')->nullable();

            $table->foreignId('renewed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();

            $table->index('subscription_id');
            $table->index('renewal_type');
            $table->index('new_end_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_renewals');
    }
};