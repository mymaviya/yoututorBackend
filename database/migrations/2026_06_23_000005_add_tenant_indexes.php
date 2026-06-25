<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->indexIfPossible('users', ['subscription_id', 'role_id'], 'users_subscription_role_idx');
        $this->indexIfPossible('subjects', ['subscription_id', 'grade_id', 'stream_id'], 'subjects_subscription_grade_stream_idx');
        $this->indexIfPossible('lessons', ['subscription_id', 'subject_id'], 'lessons_subscription_subject_idx');
        $this->indexIfPossible('question_type_assignments', ['subscription_id', 'grade_id', 'stream_id', 'subject_id'], 'qta_subscription_academic_idx');
        $this->indexIfPossible('questions', ['subscription_id', 'status', 'grade_id', 'subject_id'], 'questions_subscription_status_academic_idx');
        $this->indexIfPossible('paper_blueprints', ['subscription_id', 'grade_id', 'subject_id'], 'blueprints_subscription_academic_idx');
        $this->indexIfPossible('question_papers', ['subscription_id', 'status'], 'question_papers_subscription_status_idx');
        $this->indexIfPossible('teacher_question_tasks', ['subscription_id', 'teacher_id'], 'tasks_subscription_teacher_idx');
        $this->indexIfPossible('exam_portions', ['subscription_id', 'teacher_id'], 'exam_portions_subscription_teacher_idx');
        $this->indexIfPossible('audit_logs', ['subscription_id', 'user_id'], 'audit_logs_subscription_user_idx');
        $this->indexIfPossible('app_notifications', ['subscription_id', 'user_id', 'is_read'], 'notifications_subscription_user_read_idx');
        $this->indexIfPossible('user_devices', ['subscription_id', 'user_id', 'device_id'], 'devices_subscription_user_device_idx');
        $this->indexIfPossible('proposals', ['subscription_id', 'status'], 'proposals_subscription_status_idx');
        $this->indexIfPossible('quotations', ['subscription_id', 'status'], 'quotations_subscription_status_idx');
        $this->indexIfPossible('invoices', ['subscription_id', 'status'], 'invoices_subscription_status_idx');
    }

    public function down(): void
    {
        foreach ([
            'users' => 'users_subscription_role_idx',
            'subjects' => 'subjects_subscription_grade_stream_idx',
            'lessons' => 'lessons_subscription_subject_idx',
            'question_type_assignments' => 'qta_subscription_academic_idx',
            'questions' => 'questions_subscription_status_academic_idx',
            'paper_blueprints' => 'blueprints_subscription_academic_idx',
            'question_papers' => 'question_papers_subscription_status_idx',
            'teacher_question_tasks' => 'tasks_subscription_teacher_idx',
            'exam_portions' => 'exam_portions_subscription_teacher_idx',
            'audit_logs' => 'audit_logs_subscription_user_idx',
            'app_notifications' => 'notifications_subscription_user_read_idx',
            'user_devices' => 'devices_subscription_user_device_idx',
            'proposals' => 'proposals_subscription_status_idx',
            'quotations' => 'quotations_subscription_status_idx',
            'invoices' => 'invoices_subscription_status_idx',
        ] as $tableName => $indexName) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($indexName) {
                $table->dropIndex($indexName);
            });
        }
    }

    private function indexIfPossible(string $tableName, array $columns, string $indexName): void
    {
        if (! Schema::hasTable($tableName)) {
            return;
        }

        foreach ($columns as $column) {
            if (! Schema::hasColumn($tableName, $column)) {
                return;
            }
        }

        Schema::table($tableName, function (Blueprint $table) use ($columns, $indexName) {
            $table->index($columns, $indexName);
        });
    }
};
