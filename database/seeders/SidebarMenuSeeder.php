<?php

namespace Database\Seeders;

use App\Models\SidebarMenu;
use Illuminate\Database\Seeder;

class SidebarMenuSeeder extends Seeder
{
    public function run(): void
    {
        SidebarMenu::truncate();

        $menus = [
            ['title'=>'Dashboard','icon'=>'mdi-view-dashboard','route_name'=>'Dashboard','group_name'=>'Dashboard','parent_menu'=>null,'permission_slug'=>'dashboard.view','sort_order'=>1],

            ['title'=>'My Profile','icon'=>'mdi-account-circle','route_name'=>'profile','group_name'=>'Profile','parent_menu'=>null,'permission_slug'=>'profile.view','sort_order'=>5],

            ['title'=>'Grades','icon'=>'mdi-google-classroom','route_name'=>'grades.index','group_name'=>'Academic','parent_menu'=>'Academic','permission_slug'=>'grades.view','sort_order'=>10],
            ['title'=>'Subjects','icon'=>'mdi-book-open-page-variant','route_name'=>'subjects.index','group_name'=>'Academic','parent_menu'=>'Academic','permission_slug'=>'subjects.view','sort_order'=>11],
            ['title'=>'Lessons','icon'=>'mdi-book-education','route_name'=>'lessons.index','group_name'=>'Academic','parent_menu'=>'Academic','permission_slug'=>'lessons.view','sort_order'=>12],

            ['title'=>'Questions','icon'=>'mdi-help-circle','route_name'=>'questions.index','group_name'=>'Question Bank','parent_menu'=>'Question Bank','permission_slug'=>'questions.view','sort_order'=>20],
            ['title'=>'Create Question','icon'=>'mdi-plus-circle','route_name'=>'questions.create','group_name'=>'Question Bank','parent_menu'=>'Question Bank','permission_slug'=>'questions.create','sort_order'=>21],
            ['title'=>'Question Types','icon'=>'mdi-format-list-bulleted-type','route_name'=>'question.types','group_name'=>'Question Bank','parent_menu'=>'Question Bank','permission_slug'=>'question.types.view','sort_order'=>22],
            ['title'=>'Question Approvals','icon'=>'mdi-check-decagram','route_name'=>'question.approvals','group_name'=>'Question Bank','parent_menu'=>'Question Bank','permission_slug'=>'question.approvals','sort_order'=>23],
            ['title'=>'Language Question Editor','icon'=>'mdi-alphabetical','route_name'=>'language.questions.edit','group_name'=>'Question Bank','parent_menu'=>'Question Bank','permission_slug'=>'language.questions.edit','sort_order'=>24],

            ['title'=>'Question Papers','icon'=>'mdi-file-document-multiple','route_name'=>'papers.index','group_name'=>'Papers','parent_menu'=>'Papers','permission_slug'=>'papers.view','sort_order'=>30],
            ['title'=>'Create Paper','icon'=>'mdi-file-plus','route_name'=>'papers.creator','group_name'=>'Papers','parent_menu'=>'Papers','permission_slug'=>'papers.create','sort_order'=>31],
            ['title'=>'Paper Generator','icon'=>'mdi-auto-fix','route_name'=>'paper.generator','group_name'=>'Papers','parent_menu'=>'Papers','permission_slug'=>'papers.generate','sort_order'=>32],
            ['title'=>'Generate Paper','icon'=>'mdi-creation','route_name'=>'papers.generate','group_name'=>'Papers','parent_menu'=>'Papers','permission_slug'=>'papers.generate','sort_order'=>33],
            ['title'=>'Paper Blueprints','icon'=>'mdi-sitemap','route_name'=>'paper.blueprints','group_name'=>'Papers','parent_menu'=>'Papers','permission_slug'=>'paper.blueprints','sort_order'=>34],

            ['title'=>'Teachers','icon'=>'mdi-account-school','route_name'=>'teachers.index','group_name'=>'Teachers','parent_menu'=>'Teachers','permission_slug'=>'teachers.view','sort_order'=>40],
            ['title'=>'Teacher Tasks','icon'=>'mdi-clipboard-text','route_name'=>'teacher.tasks','group_name'=>'Teachers','parent_menu'=>'Teachers','permission_slug'=>'teacher.tasks','sort_order'=>41],

            ['title'=>'Teacher Progress','icon'=>'mdi-chart-bar','route_name'=>'teacher.progress','group_name'=>'Reports','parent_menu'=>'Reports','permission_slug'=>'teacher.progress','sort_order'=>50],
            ['title'=>'Teacher Analytics','icon'=>'mdi-chart-line','route_name'=>'teacher.analytics','group_name'=>'Reports','parent_menu'=>'Reports','permission_slug'=>'teacher.analytics','sort_order'=>51],

            ['title'=>'Exam Names','icon'=>'mdi-calendar-text','route_name'=>'exam.names','group_name'=>'Examinations','parent_menu'=>'Examinations','permission_slug'=>'exam.names','sort_order'=>60],
            ['title'=>'Exam Portions','icon'=>'mdi-format-list-checks','route_name'=>'exam.portions','group_name'=>'Examinations','parent_menu'=>'Examinations','permission_slug'=>'exam.portions','sort_order'=>61],

            ['title'=>'Teacher Dashboard','icon'=>'mdi-view-dashboard-outline','route_name'=>'teacher.dashboard','group_name'=>'Teacher Portal','parent_menu'=>'Teacher Portal','permission_slug'=>'teacher.dashboard','sort_order'=>70],
            ['title'=>'My Question Tasks','icon'=>'mdi-clipboard-check','route_name'=>'teacher.my.tasks','group_name'=>'Teacher Portal','parent_menu'=>'Teacher Portal','permission_slug'=>'teacher.my.tasks','sort_order'=>71],
            ['title'=>'My Exam Portions','icon'=>'mdi-book-check','route_name'=>'teacher.exam.portions','group_name'=>'Teacher Portal','parent_menu'=>'Teacher Portal','permission_slug'=>'teacher.exam.portions','sort_order'=>72],

            ['title'=>'Users','icon'=>'mdi-account-group','route_name'=>'users.index','group_name'=>'Administration','parent_menu'=>'Administration','permission_slug'=>'users.view','sort_order'=>80],
            ['title'=>'Roles','icon'=>'mdi-account-key','route_name'=>'roles.index','group_name'=>'Administration','parent_menu'=>'Administration','permission_slug'=>'roles.view','sort_order'=>81],
            ['title'=>'Permissions','icon'=>'mdi-key-chain','route_name'=>'permissions.index','group_name'=>'Administration','parent_menu'=>'Administration','permission_slug'=>'permissions.view','sort_order'=>82],

            ['title'=>'Security Settings','icon'=>'mdi-shield-lock','route_name'=>'security.settings','group_name'=>'Security','parent_menu'=>'Security','permission_slug'=>'security.settings','sort_order'=>90],
            ['title'=>'Login Holidays','icon'=>'mdi-calendar-remove','route_name'=>'login.holidays','group_name'=>'Security','parent_menu'=>'Security','permission_slug'=>'login.holidays','sort_order'=>91],
            ['title'=>'User Devices','icon'=>'mdi-cellphone-link','route_name'=>'user.devices','group_name'=>'Security','parent_menu'=>'Security','permission_slug'=>'user.devices','sort_order'=>92],
            ['title'=>'Audit Logs','icon'=>'mdi-history','route_name'=>'audit.logs','group_name'=>'Security','parent_menu'=>'Security','permission_slug'=>'audit.logs','sort_order'=>93],

            ['title'=>'Import Teachers','icon'=>'mdi-file-import','route_name'=>'teachers.import','group_name'=>'Imports','parent_menu'=>'Imports','permission_slug'=>'teachers.import','sort_order'=>100],
        ];

        foreach ($menus as $menu) {
            SidebarMenu::create(array_merge([
                'badge' => null,
                'badge_color' => null,
                'is_active' => true,
                'show_in_sidebar' => true,
            ], $menu));
        }
    }
}
