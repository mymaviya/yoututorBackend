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
        Schema::create('students', function (Blueprint $table) {
            $table->id();

            $table->string('admission_no')->nullable()->unique();
            $table->string('roll_no')->nullable();

            $table->string('name');
            $table->string('father_name')->nullable();
            $table->string('mother_name')->nullable();

            $table->date('dob')->nullable();
            $table->string('gender')->nullable();

            $table->string('mobile')->nullable();
            $table->string('email')->nullable();

            $table->text('address')->nullable();

            $table->foreignId('grade_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            $table->foreignId('stream_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->unique(['grade_id', 'stream_id', 'roll_no'], 'student_grade_stream_roll_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('students');
    }
};
