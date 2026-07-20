<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLE = 'teacher_assignments';

    public function up(): void
    {
        if (!Schema::hasTable(self::TABLE)) {
            return;
        }

        if (!Schema::hasColumn(self::TABLE, 'academic_year_id')) {
            Schema::table(self::TABLE, function (Blueprint $table) {
                $table->foreignId('academic_year_id')
                    ->nullable()
                    ->after('subscription_id')
                    ->constrained()
                    ->nullOnDelete();
            });
        }

        if (!Schema::hasColumn(self::TABLE, 'is_primary_teacher')) {
            Schema::table(self::TABLE, function (Blueprint $table) {
                $table->boolean('is_primary_teacher')
                    ->default(false)
                    ->after('subject_id');
            });
        }

        if (!Schema::hasColumn(self::TABLE, 'priority')) {
            Schema::table(self::TABLE, function (Blueprint $table) {
                $table->unsignedTinyInteger('priority')
                    ->default(1)
                    ->after('is_primary_teacher');
            });
        }

        if (!Schema::hasColumn(self::TABLE, 'max_periods_per_week')) {
            Schema::table(self::TABLE, function (Blueprint $table) {
                $table->unsignedTinyInteger('max_periods_per_week')
                    ->nullable()
                    ->after('priority');
            });
        }

        if (!Schema::hasColumn(self::TABLE, 'effective_from')) {
            Schema::table(self::TABLE, function (Blueprint $table) {
                $table->date('effective_from')
                    ->nullable()
                    ->after('max_periods_per_week');
            });
        }

        if (!Schema::hasColumn(self::TABLE, 'effective_to')) {
            Schema::table(self::TABLE, function (Blueprint $table) {
                $table->date('effective_to')
                    ->nullable()
                    ->after('effective_from');
            });
        }

        if (!$this->indexExists('idx_teacher_grade_subject')) {
            Schema::table(self::TABLE, function (Blueprint $table) {
                $table->index(
                    ['teacher_id', 'grade_id', 'subject_id'],
                    'idx_teacher_grade_subject'
                );
            });
        }

        if (!$this->indexExists('idx_teacher_grade_stream')) {
            Schema::table(self::TABLE, function (Blueprint $table) {
                $table->index(
                    ['grade_id', 'stream_id'],
                    'idx_teacher_grade_stream'
                );
            });
        }

        if (!$this->indexExists('idx_ta_school_year')) {
            Schema::table(self::TABLE, function (Blueprint $table) {
                $table->index(
                    ['subscription_id', 'academic_year_id'],
                    'idx_ta_school_year'
                );
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable(self::TABLE)) {
            return;
        }

        if ($this->indexExists('idx_teacher_grade_subject')) {
            Schema::table(self::TABLE, function (Blueprint $table) {
                $table->dropIndex('idx_teacher_grade_subject');
            });
        }

        if ($this->indexExists('idx_teacher_grade_stream')) {
            Schema::table(self::TABLE, function (Blueprint $table) {
                $table->dropIndex('idx_teacher_grade_stream');
            });
        }

        if ($this->indexExists('idx_ta_school_year')) {
            Schema::table(self::TABLE, function (Blueprint $table) {
                $table->dropIndex('idx_ta_school_year');
            });
        }

        if (Schema::hasColumn(self::TABLE, 'academic_year_id')) {
            Schema::table(self::TABLE, function (Blueprint $table) {
                $table->dropConstrainedForeignId('academic_year_id');
            });
        }

        $columns = collect([
            'is_primary_teacher',
            'priority',
            'max_periods_per_week',
            'effective_from',
            'effective_to',
        ])->filter(
            fn (string $column): bool => Schema::hasColumn(self::TABLE, $column)
        )->values()->all();

        if ($columns !== []) {
            Schema::table(self::TABLE, function (Blueprint $table) use ($columns) {
                $table->dropColumn($columns);
            });
        }
    }

    private function indexExists(string $indexName): bool
    {
        return collect(Schema::getIndexes(self::TABLE))
            ->contains(
                fn (array $index): bool => ($index['name'] ?? null) === $indexName
            );
    }
};