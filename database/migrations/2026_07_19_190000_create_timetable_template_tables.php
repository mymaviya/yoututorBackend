<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('timetable_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_year_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->unsignedTinyInteger('working_days')->default(6);
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['subscription_id', 'name'], 'tt_templates_subscription_name_unique');
            $table->index(['subscription_id', 'academic_year_id', 'is_active'], 'tt_templates_scope_index');
        });

        Schema::create('timetable_template_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('timetable_template_id')->constrained()->cascadeOnDelete();
            $table->foreignId('school_bell_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('weekday');
            $table->enum('slot_type', ['teaching', 'break', 'assembly', 'activity', 'blocked'])->default('teaching');
            $table->string('label', 100)->nullable();
            $table->boolean('is_available')->default(true);
            $table->boolean('is_locked')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(
                ['timetable_template_id', 'weekday', 'school_bell_id'],
                'tt_template_slots_day_bell_unique'
            );
            $table->index(['timetable_template_id', 'weekday', 'sort_order'], 'tt_template_slots_order_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('timetable_template_slots');
        Schema::dropIfExists('timetable_templates');
    }
};
