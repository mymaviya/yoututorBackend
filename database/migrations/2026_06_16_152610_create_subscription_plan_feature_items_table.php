<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_plan_feature_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('subscription_plan_id')
                ->constrained('subscription_plans')
                ->cascadeOnDelete();

            $table->string('feature_key', 100);

            $table->boolean('is_enabled')->default(true);

            $table->timestamps();

            $table->unique(
                ['subscription_plan_id', 'feature_key'],
                'plan_feature_unique'
            );

            $table->index(
                'feature_key',
                'feature_key_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_plan_feature_items');
    }
};
