<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        Permission::truncate();

        $permissions = [
            // Dashboard
            ['name' => 'View Dashboard', 'slug' => 'dashboard.view', 'group_name' => 'Dashboard'],

            // Profile
            ['name' => 'View Profile', 'slug' => 'profile.view', 'group_name' => 'Profile'],
            ['name' => 'Update Profile', 'slug' => 'profile.update', 'group_name' => 'Profile'],

            // Academic
            ['name' => 'View Grades', 'slug' => 'grades.view', 'group_name' => 'Academic'],
            ['name' => 'Create Grades', 'slug' => 'grades.create', 'group_name' => 'Academic'],
            ['name' => 'Edit Grades', 'slug' => 'grades.edit', 'group_name' => 'Academic'],
            ['name' => 'Delete Grades', 'slug' => 'grades.delete', 'group_name' => 'Academic'],

            ['name' => 'View Subjects', 'slug' => 'subjects.view', 'group_name' => 'Academic'],
            ['name' => 'Create Subjects', 'slug' => 'subjects.create', 'group_name' => 'Academic'],
            ['name' => 'Edit Subjects', 'slug' => 'subjects.edit', 'group_name' => 'Academic'],
            ['name' => 'Delete Subjects', 'slug' => 'subjects.delete', 'group_name' => 'Academic'],

            ['name' => 'View Lessons', 'slug' => 'lessons.view', 'group_name' => 'Academic'],
            ['name' => 'Create Lessons', 'slug' => 'lessons.create', 'group_name' => 'Academic'],
            ['name' => 'Edit Lessons', 'slug' => 'lessons.edit', 'group_name' => 'Academic'],
            ['name' => 'Delete Lessons', 'slug' => 'lessons.delete', 'group_name' => 'Academic'],

            // Question Bank
            ['name' => 'View Questions', 'slug' => 'questions.view', 'group_name' => 'Question Bank'],
            ['name' => 'Create Questions', 'slug' => 'questions.create', 'group_name' => 'Question Bank'],
            ['name' => 'Edit Questions', 'slug' => 'questions.edit', 'group_name' => 'Question Bank'],
            ['name' => 'Delete Questions', 'slug' => 'questions.delete', 'group_name' => 'Question Bank'],
            ['name' => 'Approve Questions', 'slug' => 'question.approvals', 'group_name' => 'Question Bank'],
            ['name' => 'Edit Language Questions', 'slug' => 'language.questions.edit', 'group_name' => 'Question Bank'],

            ['name' => 'View Question Types', 'slug' => 'question.types.view', 'group_name' => 'Question Bank'],
            ['name' => 'Create Question Types', 'slug' => 'question.types.create', 'group_name' => 'Question Bank'],
            ['name' => 'Edit Question Types', 'slug' => 'question.types.edit', 'group_name' => 'Question Bank'],
            ['name' => 'Delete Question Types', 'slug' => 'question.types.delete', 'group_name' => 'Question Bank'],

            // Papers
            ['name' => 'View Papers', 'slug' => 'papers.view', 'group_name' => 'Papers'],
            ['name' => 'Create Papers', 'slug' => 'papers.create', 'group_name' => 'Papers'],
            ['name' => 'Edit Papers', 'slug' => 'papers.edit', 'group_name' => 'Papers'],
            ['name' => 'Delete Papers', 'slug' => 'papers.delete', 'group_name' => 'Papers'],
            ['name' => 'Generate Papers', 'slug' => 'papers.generate', 'group_name' => 'Papers'],
            ['name' => 'Export Papers', 'slug' => 'papers.export', 'group_name' => 'Papers'],
            ['name' => 'Print Papers', 'slug' => 'papers.print', 'group_name' => 'Papers'],

            ['name' => 'View Paper Blueprints', 'slug' => 'paper.blueprints', 'group_name' => 'Papers'],
            ['name' => 'Create Paper Blueprints', 'slug' => 'paper.blueprints.create', 'group_name' => 'Papers'],
            ['name' => 'Edit Paper Blueprints', 'slug' => 'paper.blueprints.edit', 'group_name' => 'Papers'],
            ['name' => 'Delete Paper Blueprints', 'slug' => 'paper.blueprints.delete', 'group_name' => 'Papers'],

            // Teachers
            ['name' => 'View Teachers', 'slug' => 'teachers.view', 'group_name' => 'Teachers'],
            ['name' => 'Create Teachers', 'slug' => 'teachers.create', 'group_name' => 'Teachers'],
            ['name' => 'Edit Teachers', 'slug' => 'teachers.edit', 'group_name' => 'Teachers'],
            ['name' => 'Delete Teachers', 'slug' => 'teachers.delete', 'group_name' => 'Teachers'],
            ['name' => 'Import Teachers', 'slug' => 'teachers.import', 'group_name' => 'Teachers'],

            ['name' => 'View Teacher Tasks', 'slug' => 'teacher.tasks', 'group_name' => 'Teachers'],
            ['name' => 'Create Teacher Tasks', 'slug' => 'teacher.tasks.create', 'group_name' => 'Teachers'],
            ['name' => 'Edit Teacher Tasks', 'slug' => 'teacher.tasks.edit', 'group_name' => 'Teachers'],
            ['name' => 'Delete Teacher Tasks', 'slug' => 'teacher.tasks.delete', 'group_name' => 'Teachers'],

            // Reports
            ['name' => 'Teacher Progress Report', 'slug' => 'teacher.progress', 'group_name' => 'Reports'],
            ['name' => 'Teacher Analytics', 'slug' => 'teacher.analytics', 'group_name' => 'Reports'],

            // Exams
            ['name' => 'View Exam Names', 'slug' => 'exam.names', 'group_name' => 'Examinations'],
            ['name' => 'Create Exam Names', 'slug' => 'exam.names.create', 'group_name' => 'Examinations'],
            ['name' => 'Edit Exam Names', 'slug' => 'exam.names.edit', 'group_name' => 'Examinations'],
            ['name' => 'Delete Exam Names', 'slug' => 'exam.names.delete', 'group_name' => 'Examinations'],

            ['name' => 'View Exam Portions', 'slug' => 'exam.portions', 'group_name' => 'Examinations'],
            ['name' => 'Create Exam Portions', 'slug' => 'exam.portions.create', 'group_name' => 'Examinations'],
            ['name' => 'Edit Exam Portions', 'slug' => 'exam.portions.edit', 'group_name' => 'Examinations'],
            ['name' => 'Delete Exam Portions', 'slug' => 'exam.portions.delete', 'group_name' => 'Examinations'],

            // Teacher Portal
            ['name' => 'Teacher Dashboard', 'slug' => 'teacher.dashboard', 'group_name' => 'Teacher Portal'],
            ['name' => 'My Question Tasks', 'slug' => 'teacher.my.tasks', 'group_name' => 'Teacher Portal'],
            ['name' => 'My Exam Portions', 'slug' => 'teacher.exam.portions', 'group_name' => 'Teacher Portal'],

            // Administration
            ['name' => 'View Users', 'slug' => 'users.view', 'group_name' => 'Administration'],
            ['name' => 'Create Users', 'slug' => 'users.create', 'group_name' => 'Administration'],
            ['name' => 'Edit Users', 'slug' => 'users.edit', 'group_name' => 'Administration'],
            ['name' => 'Delete Users', 'slug' => 'users.delete', 'group_name' => 'Administration'],

            ['name' => 'View Roles', 'slug' => 'roles.view', 'group_name' => 'Administration'],
            ['name' => 'Create Roles', 'slug' => 'roles.create', 'group_name' => 'Administration'],
            ['name' => 'Edit Roles', 'slug' => 'roles.edit', 'group_name' => 'Administration'],
            ['name' => 'Delete Roles', 'slug' => 'roles.delete', 'group_name' => 'Administration'],

            ['name' => 'View Permissions', 'slug' => 'permissions.view', 'group_name' => 'Administration'],
            ['name' => 'Manage Permissions', 'slug' => 'permissions.manage', 'group_name' => 'Administration'],

            // Security
            ['name' => 'Security Settings', 'slug' => 'security.settings', 'group_name' => 'Security'],
            ['name' => 'Login Holidays', 'slug' => 'login.holidays', 'group_name' => 'Security'],
            ['name' => 'User Devices', 'slug' => 'user.devices', 'group_name' => 'Security'],
            ['name' => 'Audit Logs', 'slug' => 'audit.logs', 'group_name' => 'Security'],
        ];

        foreach ($permissions as $permission) {
            Permission::updateOrCreate(
                ['slug' => $permission['slug']],
                array_merge($permission, [
                    'show_in_sidebar' => false,
                    'menu_title' => null,
                    'menu_icon' => null,
                    'menu_route_name' => null,
                    'menu_group' => null,
                    'menu_order' => 0,
                ])
            );
        }
    }
}
