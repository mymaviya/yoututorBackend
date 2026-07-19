<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            // Dashboard
            ['name' => 'View Dashboard', 'slug' => 'dashboard.view', 'group_name' => 'Dashboard'],
            ['name' => 'View Analytics Dashboard', 'slug' => 'dashboard.analytics', 'group_name' => 'Dashboard'],

            // Profile
            ['name' => 'View Profile', 'slug' => 'profile.view', 'group_name' => 'Profile'],
            ['name' => 'Update Profile', 'slug' => 'profile.update', 'group_name' => 'Profile'],

            // Academic
            ['name' => 'View Streams', 'slug' => 'streams.view', 'group_name' => 'Academic'],
            ['name' => 'View Grades', 'slug' => 'grades.view', 'group_name' => 'Academic'],
            ['name' => 'Create Grades', 'slug' => 'grades.create', 'group_name' => 'Academic'],
            ['name' => 'Edit Grades', 'slug' => 'grades.edit', 'group_name' => 'Academic'],
            ['name' => 'Delete Grades', 'slug' => 'grades.delete', 'group_name' => 'Academic'],

            ['name' => 'View Subjects', 'slug' => 'subjects.view', 'group_name' => 'Academic'],
            ['name' => 'Create Subjects', 'slug' => 'subjects.create', 'group_name' => 'Academic'],
            ['name' => 'Edit Subjects', 'slug' => 'subjects.edit', 'group_name' => 'Academic'],
            ['name' => 'Delete Subjects', 'slug' => 'subjects.delete', 'group_name' => 'Academic'],
            ['name' => 'Manage Subject Templates', 'slug' => 'subjects.manage', 'group_name' => 'Academic'],

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

            // Teacher Portal
            ['name' => 'Teacher Dashboard', 'slug' => 'teacher.dashboard', 'group_name' => 'Teacher Portal'],
            ['name' => 'My Question Tasks', 'slug' => 'teacher.my.tasks', 'group_name' => 'Teacher Portal'],
            ['name' => 'My Exam Portions', 'slug' => 'teacher.exam.portions', 'group_name' => 'Teacher Portal'],

            // Reports
            ['name' => 'Teacher Progress Report', 'slug' => 'teacher.progress', 'group_name' => 'Reports'],
            ['name' => 'Teacher Analytics', 'slug' => 'teacher.analytics', 'group_name' => 'Reports'],

            // Examinations
            ['name' => 'View Exam Names', 'slug' => 'exam.names', 'group_name' => 'Examinations'],
            ['name' => 'Create Exam Names', 'slug' => 'exam.names.create', 'group_name' => 'Examinations'],
            ['name' => 'Edit Exam Names', 'slug' => 'exam.names.edit', 'group_name' => 'Examinations'],
            ['name' => 'Delete Exam Names', 'slug' => 'exam.names.delete', 'group_name' => 'Examinations'],

            ['name' => 'View Exam Portions', 'slug' => 'exam.portions', 'group_name' => 'Examinations'],
            ['name' => 'Create Exam Portions', 'slug' => 'exam.portions.create', 'group_name' => 'Examinations'],
            ['name' => 'Edit Exam Portions', 'slug' => 'exam.portions.edit', 'group_name' => 'Examinations'],
            ['name' => 'Delete Exam Portions', 'slug' => 'exam.portions.delete', 'group_name' => 'Examinations'],

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
            ['name' => 'Manage Sidebar Menus', 'slug' => 'manage.sidebar.menus', 'group_name' => 'Administration'],
            ['name' => 'Manage App Routes', 'slug' => 'manage.permissions', 'group_name' => 'Administration'],

            // Security
            ['name' => 'Security Settings', 'slug' => 'security.settings', 'group_name' => 'Security'],
            ['name' => 'Login Holidays', 'slug' => 'login.holidays', 'group_name' => 'Security'],
            ['name' => 'User Devices', 'slug' => 'user.devices', 'group_name' => 'Security'],
            ['name' => 'Audit Logs', 'slug' => 'audit.logs', 'group_name' => 'Security'],

            // Imports
            ['name' => 'Lesson Import', 'slug' => 'lessons.import', 'group_name' => 'Imports'],
            ['name' => 'Question Import', 'slug' => 'questions.import', 'group_name' => 'Imports'],
            ['name' => 'Blueprint Excel Import', 'slug' => 'blueprint.import', 'group_name' => 'Imports'],

            // Website / Public Site
            ['name' => 'Manage Website Settings', 'slug' => 'settings.manage', 'group_name' => 'Website'],
            ['name' => 'View CMS Pages', 'slug' => 'cms.pages.view', 'group_name' => 'Website'],
            ['name' => 'Create CMS Pages', 'slug' => 'cms.pages.create', 'group_name' => 'Website'],
            ['name' => 'Edit CMS Pages', 'slug' => 'cms.pages.edit', 'group_name' => 'Website'],
            ['name' => 'Delete CMS Pages', 'slug' => 'cms.pages.delete', 'group_name' => 'Website'],

            // SaaS Dashboard
            ['name' => 'View SaaS Dashboard', 'slug' => 'saas.dashboard', 'group_name' => 'SaaS Dashboard'],
            ['name' => 'View Revenue Analytics', 'slug' => 'revenue.analytics', 'group_name' => 'SaaS Dashboard'],
            ['name' => 'View Lead Analytics', 'slug' => 'lead.analytics', 'group_name' => 'SaaS Dashboard'],

            // SaaS CRM
            ['name' => 'View Demo Enquiries', 'slug' => 'demo.enquiries.view', 'group_name' => 'SaaS CRM'],
            ['name' => 'Manage Demo Enquiries', 'slug' => 'demo.enquiries.manage', 'group_name' => 'SaaS CRM'],

            // SaaS Subscription
            ['name' => 'Manage Subscription Plans', 'slug' => 'subscription.plans.manage', 'group_name' => 'Subscriptions'],
            ['name' => 'View Subscriptions', 'slug' => 'subscriptions.view', 'group_name' => 'Subscriptions'],
            ['name' => 'Manage Subscriptions', 'slug' => 'subscriptions.manage', 'group_name' => 'Subscriptions'],
            ['name' => 'Extend Subscriptions', 'slug' => 'subscriptions.extend', 'group_name' => 'Subscriptions'],
            ['name' => 'Activate Subscriptions', 'slug' => 'subscriptions.activate', 'group_name' => 'Subscriptions'],
            ['name' => 'Cancel Subscriptions', 'slug' => 'subscriptions.cancel', 'group_name' => 'Subscriptions'],
            ['name' => 'Suspend Subscriptions', 'slug' => 'subscriptions.suspend', 'group_name' => 'Subscriptions'],

            // SaaS License
            ['name' => 'View License Keys', 'slug' => 'licenses.view', 'group_name' => 'App License'],
            ['name' => 'Manage License Keys', 'slug' => 'licenses.manage', 'group_name' => 'App License'],
            ['name' => 'Extend License Keys', 'slug' => 'licenses.extend', 'group_name' => 'App License'],
            ['name' => 'Regenerate License Keys', 'slug' => 'licenses.regenerate', 'group_name' => 'App License'],

            // Payments
            ['name' => 'View Payments', 'slug' => 'payments.view', 'group_name' => 'Payments'],
            ['name' => 'Manage Payments', 'slug' => 'payments.manage', 'group_name' => 'Payments'],
            ['name' => 'Issue Refunds', 'slug' => 'payments.refunds', 'group_name' => 'Payments'],

            // Support
            ['name' => 'View Support Tickets', 'slug' => 'support.view', 'group_name' => 'Support'],
            ['name' => 'Manage Support Tickets', 'slug' => 'support.manage', 'group_name' => 'Support'],

            // Communication
            ['name' => 'Send Email Campaigns', 'slug' => 'email.campaigns', 'group_name' => 'Communication'],
            ['name' => 'Send Notifications', 'slug' => 'notifications.send', 'group_name' => 'Communication'],

            // System
            ['name' => 'System Settings', 'slug' => 'system.settings', 'group_name' => 'System'],
            ['name' => 'Manage Backups', 'slug' => 'system.backups', 'group_name' => 'System'],
            ['name' => 'Manage Integrations', 'slug' => 'system.integrations', 'group_name' => 'System'],

            ['name' => 'View Proposals', 'slug' => 'proposals.view', 'group_name' => 'CRM & Billing'],
            ['name' => 'Create Proposals', 'slug' => 'proposals.create', 'group_name' => 'CRM & Billing'],
            ['name' => 'Edit Proposals', 'slug' => 'proposals.edit', 'group_name' => 'CRM & Billing'],
            ['name' => 'Delete Proposals', 'slug' => 'proposals.delete', 'group_name' => 'CRM & Billing'],

            ['name' => 'View Quotations', 'slug' => 'quotations.view', 'group_name' => 'CRM & Billing'],
            ['name' => 'Edit Quotations', 'slug' => 'quotations.edit', 'group_name' => 'CRM & Billing'],

            ['name' => 'View Invoices', 'slug' => 'invoices.view', 'group_name' => 'CRM & Billing'],
            ['name' => 'Edit Invoices', 'slug' => 'invoices.edit', 'group_name' => 'CRM & Billing'],

            [
                'name' => 'Academic Planning Dashboard',
                'slug' => 'academic.planning.dashboard',
                'group_name' => 'Academic Planning',
            ],

            [
                'name' => 'Manage Academic Years',
                'slug' => 'academic.years',
                'group_name' => 'Academic Planning',
            ],

            [
                'name' => 'Manage Timetable Templates',
                'slug' => 'timetable.templates',
                'group_name' => 'Academic Planning',
            ],

            [
                'name' => 'Manage Teacher Assignment',
                'slug' => 'teacher.assignment',
                'group_name' => 'Academic Planning',
            ],

            [
                'name' => 'Manage Teacher Availability',
                'slug' => 'teacher.availability',
                'group_name' => 'Academic Planning',
            ],

            [
                'name' => 'Manage Teacher Workload',
                'slug' => 'teacher.workload',
                'group_name' => 'Academic Planning',
            ],

            [
                'name' => 'Manage Subject Allocation',
                'slug' => 'subject.allocation',
                'group_name' => 'Academic Planning',
            ],

            [
                'name' => 'Manage Parallel Groups',
                'slug' => 'parallel.groups',
                'group_name' => 'Academic Planning',
            ],

            [
                'name' => 'Manage Timetable Rules',
                'slug' => 'timetable.rules',
                'group_name' => 'Academic Planning',
            ],

            [
                'name' => 'Generate Timetable',
                'slug' => 'timetable.generate',
                'group_name' => 'Academic Planning',
            ],

            [
                'name' => 'Publish Timetable',
                'slug' => 'timetable.publish',
                'group_name' => 'Academic Planning',
            ],

            [
                'name' => 'Teacher Substitution',
                'slug' => 'teacher.substitution',
                'group_name' => 'Academic Planning',
            ],

            [
                'name' => 'View Teacher Timetable',
                'slug' => 'teacher.timetable',
                'group_name' => 'Academic Planning',
            ],

        ];


        $permissions = array_merge($permissions, [
            // Extra route/menu permissions
            ['name' => 'View Streams', 'slug' => 'streams.view', 'group_name' => 'Academic'],
            ['name' => 'Import Lessons', 'slug' => 'lessons.import', 'group_name' => 'Imports'],
            ['name' => 'Import Questions', 'slug' => 'questions.import', 'group_name' => 'Imports'],
            ['name' => 'Import Blueprint Excel', 'slug' => 'blueprint.import', 'group_name' => 'Imports'],
            ['name' => 'View Question Type Templates', 'slug' => 'question.type.templates.view', 'group_name' => 'Question Bank'],
            ['name' => 'Manage Question Type Templates', 'slug' => 'question.type.templates.manage', 'group_name' => 'Question Bank'],
            ['name' => 'Generate Paper Preview', 'slug' => 'paper.generator.preview', 'group_name' => 'Papers'],
            ['name' => 'Generate Paper From Blueprint', 'slug' => 'paper.generator.generate', 'group_name' => 'Papers'],
            ['name' => 'Manage User Security', 'slug' => 'users.security.manage', 'group_name' => 'Security'],
            ['name' => 'Manage User Devices', 'slug' => 'user.devices.manage', 'group_name' => 'Security'],
            ['name' => 'Trust User Devices', 'slug' => 'user.devices.trust', 'group_name' => 'Security'],
            ['name' => 'Block User Devices', 'slug' => 'user.devices.block', 'group_name' => 'Security'],
            ['name' => 'CRM Dashboard', 'slug' => 'crm.dashboard', 'group_name' => 'CRM & Billing'],
            ['name' => 'Create Quotations', 'slug' => 'quotations.create', 'group_name' => 'CRM & Billing'],
            ['name' => 'Delete Quotations', 'slug' => 'quotations.delete', 'group_name' => 'CRM & Billing'],
            ['name' => 'Create Invoices', 'slug' => 'invoices.create', 'group_name' => 'CRM & Billing'],
            ['name' => 'Delete Invoices', 'slug' => 'invoices.delete', 'group_name' => 'CRM & Billing'],

            [
                'name' => 'View Premium Question Bank',
                'slug' => 'premium.question.bank.view',
                'group_name' => 'Premium Question Bank',
            ],
            [
                'name' => 'Import Premium Questions',
                'slug' => 'premium.question.bank.import',
                'group_name' => 'Premium Question Bank',
            ],
            [
                'name' => 'Manage Question Bank Packages',
                'slug' => 'question.bank.packages.manage',
                'group_name' => 'Premium Question Bank',
            ],
            [
                'name' => 'Manage Master Questions',
                'slug' => 'master.questions.manage',
                'group_name' => 'Premium Question Bank',
            ],
            [
                'name' => 'Manage Question Bank Purchases',
                'slug' => 'question.bank.purchases.manage',
                'group_name' => 'Premium Question Bank',
            ],
            [
                'name' => 'Manage Bell Schedule',
                'slug' => 'bell_schedule_management',
                'group_name' => 'Bell Schedule',
            ],

            [
                'name' => 'View AI Paper Generator',
                'slug' => 'ai.paper.generator.index',
                'group_name' => 'AI Paper Generator',
            ],
            [
                'name' => 'Generate AI Papers',
                'slug' => 'ai.paper.generator.create',
                'group_name' => 'AI Paper Generator',
            ],
            [
                'name' => 'Delete AI Papers',
                'slug' => 'ai.paper.generator.delete',
                'group_name' => 'AI Paper Generator',
            ],
            [
                'name' => 'Save AI Questions To Question Bank',
                'slug' => 'ai.paper.generator.save.questions',
                'group_name' => 'AI Paper Generator',
            ],


        ]);

        $permissions = collect($permissions)
            ->unique('slug')
            ->values()
            ->all();

        foreach ($permissions as $permission) {
            $payload = [
                'name' => $permission['name'],
                'group_name' => $permission['group_name'],
            ];

            if (Schema::hasColumn('permissions', 'show_in_sidebar')) {
                $payload['show_in_sidebar'] = false;
            }

            if (Schema::hasColumn('permissions', 'menu_title')) {
                $payload['menu_title'] = null;
            }

            if (Schema::hasColumn('permissions', 'menu_icon')) {
                $payload['menu_icon'] = null;
            }

            if (Schema::hasColumn('permissions', 'menu_route_name')) {
                $payload['menu_route_name'] = null;
            }

            if (Schema::hasColumn('permissions', 'menu_group')) {
                $payload['menu_group'] = null;
            }

            if (Schema::hasColumn('permissions', 'menu_order')) {
                $payload['menu_order'] = 0;
            }

            if (Schema::hasColumn('permissions', 'is_active')) {
                $payload['is_active'] = true;
            }

            Permission::updateOrCreate(
                ['slug' => $permission['slug']],
                $payload
            );
        }
    }
}
