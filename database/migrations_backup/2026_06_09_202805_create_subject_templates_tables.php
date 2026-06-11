<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subject_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('subject_template_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subject_template_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->string('subject_name');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subject_template_items');
        Schema::dropIfExists('subject_templates');
    }
};
