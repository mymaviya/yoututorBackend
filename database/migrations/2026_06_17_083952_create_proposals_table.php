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
        Schema::create('proposals', function (Blueprint $table) {
            $table->id();

            $table->foreignId('proposal_template_id')->nullable()->constrained()->nullOnDelete();

            $table->string('proposal_no')->unique();
            $table->string('client_name');
            $table->string('client_email')->nullable();
            $table->string('client_phone')->nullable();
            $table->string('organization_name')->nullable();

            $table->string('project_name');
            $table->string('project_type')->nullable();

            $table->integer('timeline_days')->nullable();
            $table->boolean('gst_applicable')->default(true);
            $table->decimal('gst_percentage', 5, 2)->default(18.00);

            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('gst_amount', 12, 2)->default(0);
            $table->decimal('grand_total', 12, 2)->default(0);

            $table->text('payment_terms')->nullable();
            $table->text('notes')->nullable();

            $table->enum('status', [
                'draft',
                'sent',
                'approved',
                'rejected',
                'change_requested',
                'converted_to_quotation'
            ])->default('draft');

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamp('sent_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('proposals');
    }
};
