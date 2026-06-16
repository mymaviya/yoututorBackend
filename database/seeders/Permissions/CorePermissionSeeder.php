<?php

namespace Database\Seeders\Permissions;

use Illuminate\Database\Seeder;

class CorePermissionSeeder extends Seeder
{
    use SeedsPermissions;

    public function run(): void
    {
        $permissions = [
            ['name' => 'View Dashboard', 'slug' => 'dashboard.view', 'group_name' => 'Dashboard'],
            ['name' => 'View Profile', 'slug' => 'profile.view', 'group_name' => 'Profile'],
            ['name' => 'Update Profile', 'slug' => 'profile.update', 'group_name' => 'Profile'],
            ['name' => 'Teacher Progress Report', 'slug' => 'teacher.progress', 'group_name' => 'Reports'],
            ['name' => 'Teacher Analytics', 'slug' => 'teacher.analytics', 'group_name' => 'Reports'],
            ['name' => 'View Analytics Dashboard', 'slug' => 'dashboard.analytics', 'group_name' => 'Dashboard'],
        ];

        $this->seedPermissions($permissions);
    }
}
