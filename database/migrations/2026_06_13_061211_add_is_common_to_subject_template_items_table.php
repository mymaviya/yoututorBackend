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
        Schema::table('subject_template_items', function (Blueprint $table) {
            $table->boolean('is_common')
                ->default(false)
                ->after('subject_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subject_template_items', function (Blueprint $table) {
            $table->dropColumn('is_common');
        });
    }
};
