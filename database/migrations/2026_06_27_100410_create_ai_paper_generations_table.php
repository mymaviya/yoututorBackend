<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_paper_generations', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('subscription_id');
            $table->unsignedBigInteger('paper_blueprint_id');
            $table->unsignedBigInteger('created_by')->nullable();

            $table->string('title')->nullable();
            $table->string('status')->default('draft');
            // draft, generating, generated, failed, converted

            $table->string('language')->default('en');
            $table->string('difficulty')->nullable();

            $table->integer('total_questions')->default(0);
            $table->decimal('total_marks', 8, 2)->default(0);

            $table->longText('prompt')->nullable();
            $table->longText('ai_response')->nullable();
            $table->text('error_message')->nullable();

            $table->timestamps();

            $table->foreign('subscription_id', 'ai_pg_subscription_fk')
                ->references('id')
                ->on('subscriptions')
                ->cascadeOnDelete();

            $table->foreign('paper_blueprint_id', 'ai_pg_blueprint_fk')
                ->references('id')
                ->on('paper_blueprints')
                ->cascadeOnDelete();

            $table->foreign('created_by', 'ai_pg_created_by_fk')
                ->references('id')
                ->on('users')
                ->nullOnDelete();

            $table->index('subscription_id', 'ai_pg_subscription_idx');
            $table->index('paper_blueprint_id', 'ai_pg_blueprint_idx');
            $table->index('status', 'ai_pg_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_paper_generations');
    }
};