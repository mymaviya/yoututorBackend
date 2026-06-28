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
        Schema::create('subscription_plan_question_bank_packages', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('subscription_plan_id');
            $table->unsignedBigInteger('question_bank_package_id');

            $table->timestamps();

            $table->foreign('subscription_plan_id', 'sp_qbp_plan_fk')
                ->references('id')
                ->on('subscription_plans')
                ->cascadeOnDelete();

            $table->foreign('question_bank_package_id', 'sp_qbp_package_fk')
                ->references('id')
                ->on('question_bank_packages')
                ->cascadeOnDelete();

            $table->unique([
                'subscription_plan_id',
                'question_bank_package_id',
            ], 'sp_qbp_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscription_plan_question_bank_packages');
    }
};
