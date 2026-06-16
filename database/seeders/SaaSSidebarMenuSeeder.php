<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SaaSSidebarMenuSeeder extends Seeder
{
    /**
     * Complete sidebar menu entries for SaaS modules.
     * Schema safe: only inserts columns that exist in sidebar_menus.
     */
    public function run(): void
    {
        if (!Schema::hasTable('sidebar_menus')) {
            return;
        }

        $menus = [
            ['title' => 'SaaS Dashboard', 'route' => '/admin/saas-dashboard', 'icon' => 'mdi-view-dashboard-variant', 'permission_slug' => 'saas.dashboard', 'parent_id' => null, 'sort_order' => 800, 'is_active' => true, 'show_in_sidebar' => true, 'badge' => null, 'badge_color' => null],
            ['title' => 'Demo Enquiries', 'route' => '/admin/demo-enquiries', 'icon' => 'mdi-account-star', 'permission_slug' => 'demo.enquiries.view', 'parent_id' => null, 'sort_order' => 810, 'is_active' => true, 'show_in_sidebar' => true, 'badge' => null, 'badge_color' => null],
            ['title' => 'Subscriptions', 'route' => '/admin/subscriptions', 'icon' => 'mdi-card-account-details-star', 'permission_slug' => 'subscriptions.view', 'parent_id' => null, 'sort_order' => 820, 'is_active' => true, 'show_in_sidebar' => true, 'badge' => null, 'badge_color' => null],
            ['title' => 'Payments', 'route' => '/admin/payments', 'icon' => 'mdi-cash-multiple', 'permission_slug' => 'payments.view', 'parent_id' => null, 'sort_order' => 830, 'is_active' => true, 'show_in_sidebar' => true, 'badge' => null, 'badge_color' => null],
            ['title' => 'Licenses', 'route' => '/admin/licenses', 'icon' => 'mdi-license', 'permission_slug' => 'licenses.view', 'parent_id' => null, 'sort_order' => 840, 'is_active' => true, 'show_in_sidebar' => true, 'badge' => null, 'badge_color' => null],
            ['title' => 'Website Settings', 'route' => '/admin/settings', 'icon' => 'mdi-cog', 'permission_slug' => 'settings.manage', 'parent_id' => null, 'sort_order' => 900, 'is_active' => true, 'show_in_sidebar' => true, 'badge' => null, 'badge_color' => null],
            ['title' => 'CMS Pages', 'route' => '/admin/cms-pages', 'icon' => 'mdi-file-document-edit', 'permission_slug' => 'cms.pages.view', 'parent_id' => null, 'sort_order' => 910, 'is_active' => true, 'show_in_sidebar' => true, 'badge' => null, 'badge_color' => null],
            ['title' => 'Support Tickets', 'route' => '/admin/support-tickets', 'icon' => 'mdi-lifebuoy', 'permission_slug' => 'support.view', 'parent_id' => null, 'sort_order' => 920, 'is_active' => true, 'show_in_sidebar' => true, 'badge' => null, 'badge_color' => null],
        ];

        foreach ($menus as $menu) {
            $payload = [];

            foreach ($menu as $column => $value) {
                if (Schema::hasColumn('sidebar_menus', $column)) {
                    $payload[$column] = $value;
                }
            }

            if (Schema::hasColumn('sidebar_menus', 'created_at')) {
                $payload['created_at'] = now();
            }
            if (Schema::hasColumn('sidebar_menus', 'updated_at')) {
                $payload['updated_at'] = now();
            }

            $unique = [];
            if (Schema::hasColumn('sidebar_menus', 'route')) {
                $unique['route'] = $menu['route'];
            } elseif (Schema::hasColumn('sidebar_menus', 'title')) {
                $unique['title'] = $menu['title'];
            }

            if (!empty($unique)) {
                DB::table('sidebar_menus')->updateOrInsert($unique, $payload);
            }
        }
    }
}
