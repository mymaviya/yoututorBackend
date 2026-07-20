<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $seeders = [
            // Permissions must exist before role-permission mapping.
            PermissionSeeder::class,

            // Plans must exist before feature items are attached.
            SubscriptionPlanSeeder::class,
            SubscriptionPlanFeatureSeeder::class,

            // Sidebar menus use permission_slug and feature_key.
            SidebarMenuSeeder::class,
            TimetableSidebarMenuSeeder::class,

            // Assign seeded permissions to roles.
            RolePermissionSeeder::class,
        ];

        foreach ($seeders as $seeder) {
            if (class_exists($seeder)) {
                $this->call($seeder);
            }
        }
    }
}
