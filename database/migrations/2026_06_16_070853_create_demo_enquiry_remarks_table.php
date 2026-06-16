<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('demo_enquiry_remarks', function (Blueprint $table) {
            $table->id();

            $table->foreignId('demo_enquiry_id')
                ->constrained('demo_enquiries')
                ->cascadeOnDelete();

            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->enum('type', [
                'remark',
                'call',
                'whatsapp',
                'email',
                'follow_up',
                'status_change',
                'demo_scheduled',
                'trial_started',
                'converted',
            ])->default('remark');

            $table->text('remark');

            $table->dateTime('follow_up_at')->nullable();

            $table->timestamps();

            $table->index(['demo_enquiry_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('demo_enquiry_remarks');
    }
};