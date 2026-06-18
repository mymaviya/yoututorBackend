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
        Schema::create('quotations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('proposal_id')->nullable()->constrained()->nullOnDelete();

            $table->string('quotation_no')->unique();

            $table->string('client_name');
            $table->string('organization_name')->nullable();
            $table->string('project_name');

            $table->date('quotation_date')->nullable();
            $table->date('valid_until')->nullable();

            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('gst_percentage', 5, 2)->default(18);
            $table->decimal('gst_amount', 12, 2)->default(0);
            $table->decimal('grand_total', 12, 2)->default(0);

            $table->enum('status', [
                'draft',
                'sent',
                'accepted',
                'rejected',
                'converted_to_invoice'
            ])->default('draft');

            $table->text('terms')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quotations');
    }
};
