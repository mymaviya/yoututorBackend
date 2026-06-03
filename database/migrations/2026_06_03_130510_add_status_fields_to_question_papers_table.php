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
        Schema::table('question_papers', function (Blueprint $table) {
            $table->enum('status', [
                'draft',
                'finalized',
                'printed',
                'archived'
            ])->default('draft')->after('is_published');

            $table->timestamp('finalized_at')->nullable();
            $table->unsignedBigInteger('finalized_by')->nullable();

            $table->timestamp('printed_at')->nullable();
            $table->unsignedBigInteger('printed_by')->nullable();

            $table->timestamp('archived_at')->nullable();
            $table->unsignedBigInteger('archived_by')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('question_papers', function (Blueprint $table) {
            //
        });
    }
};
