<?php

namespace Database\Seeders;

use App\Models\SidebarMenu;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class TimetableSidebarMenuSeeder extends Seeder
{
    public function run(): void
    {
        $menus = [
            [
                'title' => 'Smart Timetable Generator',
                'icon' => 'mdi-calendar-cog',
                'route' => '/academic-planning/timetable-generator',
                'route_name' => 'timetable.generator',
                'sort_order' => 106,
            ],
            [
                'title' => 'Batch Timetable Generator',
                'icon' => 'mdi-calendar-multiple-check',
                'route' => '/academic-planning/timetable-batch-generator',
                'route_name' => 'timetable.batch-generator',
                'sort_order' => 107,
            ],
            [
                'title' => 'Weekly Timetable Editor',
                'icon' => 'mdi-calendar-edit',
                'route' => '/academic-planning/timetable-editor',
                'route_name' => 'timetable.editor',
                'sort_order' => 108,
            ],
            [
                'title' => 'Timetable Reports',
                'icon' => 'mdi-chart-timeline-variant',
                'route' => '/academic-planning/timetable-reports',
                'route_name' => 'timetable.reports',
                'sort_order' => 109,
            ],
            [
                'title' => 'Timetable Templates',
                'icon' => 'mdi-calendar-text',
                'route' => '/academic-planning/timetable-templates',
                'route_name' => 'timetable.templates',
                'sort_order' => 110,
            ],
            [
                'title' => 'Timetable Rules',
                'icon' => 'mdi-calendar-lock',
                'route' => '/academic-planning/timetable-rules',
                'route_name' => 'timetable.rules',
                'sort_order' => 111,
            ],
            [
                'title' => 'Timetable Rooms',
                'icon' => 'mdi-door-open',
                'route' => '/academic-planning/timetable-rooms',
                'route_name' => 'timetable.rooms',
                'sort_order' => 112,
            ],
            [
                'title' => 'Parallel Groups',
                'icon' => 'mdi-vector-arrange-above',
                'route' => '/academic-planning/parallel-groups',
                'route_name' => 'timetable.parallel-groups',
                'sort_order' => 113,
            ],
        ];

        foreach ($menus as $menu) {
            $payload = array_merge($menu, [
                'group_name' => 'Academic Planning',
                'parent_menu' => 'Academic Planning',
                'permission_slug' => null,
                'feature_key' => 'academic_planning',
                'role_slug' => null,
                'badge' => null,
                'badge_color' => null,
                'is_active' => true,
                'show_in_sidebar' => true,
            ]);

            $safePayload = [];

            foreach ($payload as $column => $value) {
                if (Schema::hasColumn('sidebar_menus', $column)) {
                    $safePayload[$column] = $value;
                }
            }

            SidebarMenu::updateOrCreate(
                ['route_name' => $menu['route_name']],
                $safePayload
            );
        }
    }
}
