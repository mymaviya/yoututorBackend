<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Permission;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [

            // DASHBOARD
            [
                'name' => 'View Dashboard',
                'slug' => 'view_dashboard',
            ],

            [
                'name' => 'View Analytics',
                'slug' => 'view_analytics',
            ],

            // TEACHERS
            [
                'name' => 'Manage Teachers',
                'slug' => 'manage_teachers',
            ],

            // QUESTIONS
            [
                'name' => 'Create Questions',
                'slug' => 'create_questions',
            ],

            [
                'name' => 'Edit Questions',
                'slug' => 'edit_questions',
            ],

            [
                'name' => 'Delete Questions',
                'slug' => 'delete_questions',
            ],

            [
                'name' => 'Approve Questions',
                'slug' => 'approve_questions',
            ],

            [
                'name' => 'Reject Questions',
                'slug' => 'reject_questions',
            ],

            [
                'name' => 'View Question Bank',
                'slug' => 'view_question_bank',
            ],

            // QUESTION TYPES
            [
                'name' => 'Manage Question Types',
                'slug' => 'manage_question_types',
            ],

            // BLUEPRINTS
            [
                'name' => 'Manage Blueprints',
                'slug' => 'manage_blueprints',
            ],

            // PAPERS
            [
                'name' => 'Generate Papers',
                'slug' => 'generate_papers',
            ],

            [
                'name' => 'Download Papers',
                'slug' => 'download_papers',
            ],

            // EXAMS
            [
                'name' => 'Manage Exams',
                'slug' => 'manage_exams',
            ],

            // STUDENTS
            [
                'name' => 'View Students',
                'slug' => 'view_students',
            ],

            // NOTIFICATIONS
            [
                'name' => 'Manage Notifications',
                'slug' => 'manage_notifications',
            ],

            // ROLE & PERMISSION
            [
                'name' => 'Manage Roles',
                'slug' => 'manage_roles',
            ],

            [
                'name' => 'Manage Permissions',
                'slug' => 'manage_permissions',
            ],
        ];

        foreach ($permissions as $permission) {

            Permission::updateOrCreate(
                ['slug' => $permission['slug']],
                [
                    'name' => $permission['name'],
                ]
            );
        }
    }
}
