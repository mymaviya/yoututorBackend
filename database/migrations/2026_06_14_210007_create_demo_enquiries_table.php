<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('demo_enquiries', function (Blueprint $table) {
            $table->id();
            $table->string('school_name');
            $table->string('contact_person');
            $table->string('mobile');
            $table->string('email');
            $table->string('school_type')->nullable();
            $table->string('interested_plan')->nullable();
            $table->text('message')->nullable();
            $table->enum('status', ['new', 'contacted', 'demo_started', 'converted', 'rejected'])->default('new');
            $table->text('admin_note')->nullable();
            $table->timestamp('demo_started_at')->nullable();
            $table->timestamp('demo_ends_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('demo_enquiries');
    }
};