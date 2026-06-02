<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\SidebarMenu;

class SidebarMenuSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        SidebarMenu::truncate();

        $menus = [
            ['title' => 'Dashboard', 'icon' => 'mdi-view-dashboard', 'route_name' => 'Dashboard', 'group_name' => null, 'permission_slug' => 'view_dashboard', 'sort_order' => 1],

            ['title' => 'All Teachers', 'icon' => 'mdi-account-group', 'route_name' => 'teachers.index', 'group_name' => 'Teachers', 'permission_slug' => 'manage_teachers', 'sort_order' => 10],
            ['title' => 'Teacher Analytics', 'icon' => 'mdi-chart-line', 'route_name' => 'teacher.analytics', 'group_name' => 'Teachers', 'permission_slug' => 'view_analytics', 'sort_order' => 11],
            ['title' => 'Teachers Import', 'icon' => 'mdi-file-upload', 'route_name' => 'teachers.import', 'group_name' => 'Teachers', 'permission_slug' => 'manage_teachers', 'sort_order' => 12],

            ['title' => 'All Questions', 'icon' => 'mdi-format-list-bulleted', 'route_name' => 'questions.index', 'group_name' => 'Question Bank', 'permission_slug' => 'view_question_bank', 'sort_order' => 20],
            ['title' => 'Add Question', 'icon' => 'mdi-plus-circle', 'route_name' => 'questions.create', 'group_name' => 'Question Bank', 'permission_slug' => 'create_questions', 'sort_order' => 21],
            ['title' => 'Question Approval', 'icon' => 'mdi-check-decagram', 'route_name' => 'question.approvals', 'group_name' => 'Question Bank', 'permission_slug' => 'approve_questions', 'sort_order' => 22],
            ['title' => 'Question Types', 'icon' => 'mdi-format-list-bulleted-type', 'route_name' => 'question.types', 'group_name' => 'Question Bank', 'permission_slug' => 'manage_question_types', 'sort_order' => 23],
            ['title' => 'Paper Blueprints', 'icon' => 'mdi-table-cog', 'route_name' => 'paper.blueprints', 'group_name' => 'Question Bank', 'permission_slug' => 'manage_blueprints', 'sort_order' => 24],

            ['title' => 'All Papers', 'icon' => 'mdi-file-document-outline', 'route_name' => 'papers.index', 'group_name' => 'Question Papers', 'permission_slug' => 'download_papers', 'sort_order' => 30],
            ['title' => 'Generate Paper', 'icon' => 'mdi-auto-fix', 'route_name' => 'papers.generate', 'group_name' => 'Question Papers', 'permission_slug' => 'generate_papers', 'sort_order' => 31],

            ['title' => 'Roles', 'icon' => 'mdi-shield-account', 'route_name' => 'roles.index', 'group_name' => 'Administration', 'permission_slug' => 'manage_roles', 'sort_order' => 80],
            ['title' => 'Permissions', 'icon' => 'mdi-shield-check', 'route_name' => 'permissions.index', 'group_name' => 'Administration', 'permission_slug' => 'manage_permissions', 'sort_order' => 81],
        ];

        foreach ($menus as $menu) {
            SidebarMenu::create($menu);
        }
    }
}
