<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            if (! Schema::hasColumn('subscriptions', 'school_address')) {
                $table->text('school_address')->nullable()->after('school_name');
            }

            if (! Schema::hasColumn('subscriptions', 'school_phone')) {
                $table->string('school_phone')->nullable()->after('school_address');
            }

            if (! Schema::hasColumn('subscriptions', 'school_email')) {
                $table->string('school_email')->nullable()->after('school_phone');
            }

            if (! Schema::hasColumn('subscriptions', 'school_logo')) {
                $table->string('school_logo')->nullable()->after('school_email');
            }

            if (! Schema::hasColumn('subscriptions', 'academic_session')) {
                $table->string('academic_session')->nullable()->after('school_logo');
            }

            if (! Schema::hasColumn('subscriptions', 'principal_name')) {
                $table->string('principal_name')->nullable()->after('academic_session');
            }

            if (! Schema::hasColumn('subscriptions', 'affiliation_no')) {
                $table->string('affiliation_no')->nullable()->after('principal_name');
            }

            if (! Schema::hasColumn('subscriptions', 'school_website')) {
                $table->string('school_website')->nullable()->after('affiliation_no');
            }
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $columns = [
                'school_address',
                'school_phone',
                'school_email',
                'school_logo',
                'academic_session',
                'principal_name',
                'affiliation_no',
                'school_website',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('subscriptions', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
