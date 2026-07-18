<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }
        
        $this->backfillFromUser('questions', 'created_by');
        $this->backfillFromUser('question_papers', 'created_by');
        $this->backfillFromUser('teacher_question_tasks', 'teacher_id');
        $this->backfillFromUser('exam_portions', 'teacher_id');
        $this->backfillFromUser('audit_logs', 'user_id');
        $this->backfillFromUser('app_notifications', 'user_id');
        $this->backfillFromUser('user_devices', 'user_id');
        $this->backfillFromUser('proposals', 'created_by');
        $this->backfillFromUser('quotations', 'created_by');
        $this->backfillFromUser('invoices', 'created_by');
        $this->backfillFromUser('invoice_payments', 'received_by');
        $this->backfillFromUser('proposal_versions', 'created_by');

        $this->backfillFromQuestionPapers();
        $this->backfillLessonsFromSubjects();
        $this->backfillSubjectsFromQuestions();
        $this->backfillQuestionTypeAssignmentsFromSubjects();
        $this->backfillTeacherAssignmentsFromUsers();
        $this->backfillTeacherGradesFromUsers();
        $this->backfillBlueprintsFromQuestions();
    }

    public function down(): void
    {
        // No destructive rollback. Backfilled tenant ids should remain valid data.
    }

    private function backfillFromUser(string $tableName, string $userColumn): void
    {
        if (! $this->hasColumns($tableName, ['subscription_id', $userColumn]) || ! Schema::hasTable('users')) {
            return;
        }

        DB::statement("\n            UPDATE {$tableName} target\n            JOIN users u ON u.id = target.{$userColumn}\n            SET target.subscription_id = u.subscription_id\n            WHERE target.subscription_id IS NULL\n              AND u.subscription_id IS NOT NULL\n        ");
    }

    private function backfillFromQuestionPapers(): void
    {
        if (
            ! $this->hasColumns('question_paper_questions', ['question_paper_id'])
            || ! $this->hasColumns('question_papers', ['subscription_id'])
        ) {
            return;
        }

        // Child rows are scoped through their parent question paper. No child subscription_id is required.
    }

    private function backfillLessonsFromSubjects(): void
    {
        if (
            ! $this->hasColumns('lessons', ['subscription_id', 'subject_id'])
            || ! $this->hasColumns('subjects', ['subscription_id'])
        ) {
            return;
        }

        DB::statement("\n            UPDATE lessons l\n            JOIN subjects s ON s.id = l.subject_id\n            SET l.subscription_id = s.subscription_id\n            WHERE l.subscription_id IS NULL\n              AND s.subscription_id IS NOT NULL\n        ");
    }

    private function backfillSubjectsFromQuestions(): void
    {
        if (
            ! $this->hasColumns('subjects', ['subscription_id'])
            || ! $this->hasColumns('questions', ['subscription_id', 'subject_id'])
        ) {
            return;
        }

        DB::statement("\n            UPDATE subjects s\n            JOIN (\n                SELECT subject_id, MIN(subscription_id) AS subscription_id\n                FROM questions\n                WHERE subscription_id IS NOT NULL\n                GROUP BY subject_id\n            ) q ON q.subject_id = s.id\n            SET s.subscription_id = q.subscription_id\n            WHERE s.subscription_id IS NULL\n        ");
    }

    private function backfillQuestionTypeAssignmentsFromSubjects(): void
    {
        if (
            ! $this->hasColumns('question_type_assignments', ['subscription_id', 'subject_id'])
            || ! $this->hasColumns('subjects', ['subscription_id'])
        ) {
            return;
        }

        DB::statement("\n            UPDATE question_type_assignments qta\n            JOIN subjects s ON s.id = qta.subject_id\n            SET qta.subscription_id = s.subscription_id\n            WHERE qta.subscription_id IS NULL\n              AND s.subscription_id IS NOT NULL\n        ");
    }

    private function backfillTeacherAssignmentsFromUsers(): void
    {
        if (! $this->hasColumns('teacher_assignments', ['subscription_id', 'teacher_id']) || ! Schema::hasTable('users')) {
            return;
        }

        DB::statement("\n            UPDATE teacher_assignments ta\n            JOIN users u ON u.id = ta.teacher_id\n            SET ta.subscription_id = u.subscription_id\n            WHERE ta.subscription_id IS NULL\n              AND u.subscription_id IS NOT NULL\n        ");
    }

    private function backfillTeacherGradesFromUsers(): void
    {
        if (! $this->hasColumns('teacher_grades', ['subscription_id', 'teacher_id']) || ! Schema::hasTable('users')) {
            return;
        }

        DB::statement("\n            UPDATE teacher_grades tg\n            JOIN users u ON u.id = tg.teacher_id\n            SET tg.subscription_id = u.subscription_id\n            WHERE tg.subscription_id IS NULL\n              AND u.subscription_id IS NOT NULL\n        ");
    }

    private function backfillBlueprintsFromQuestions(): void
    {
        if (
            ! $this->hasColumns('paper_blueprints', ['subscription_id', 'grade_id', 'subject_id'])
            || ! $this->hasColumns('questions', ['subscription_id', 'grade_id', 'subject_id'])
        ) {
            return;
        }

        DB::statement("\n            UPDATE paper_blueprints pb\n            JOIN (\n                SELECT grade_id, subject_id, MIN(subscription_id) AS subscription_id\n                FROM questions\n                WHERE subscription_id IS NOT NULL\n                GROUP BY grade_id, subject_id\n            ) q ON q.grade_id = pb.grade_id AND q.subject_id = pb.subject_id\n            SET pb.subscription_id = q.subscription_id\n            WHERE pb.subscription_id IS NULL\n        ");
    }

    private function hasColumns(string $tableName, array $columns): bool
    {
        if (! Schema::hasTable($tableName)) {
            return false;
        }

        foreach ($columns as $column) {
            if (! Schema::hasColumn($tableName, $column)) {
                return false;
            }
        }

        return true;
    }
};
