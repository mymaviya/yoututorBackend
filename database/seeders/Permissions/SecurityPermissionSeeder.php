<?php

namespace Database\Seeders\Permissions;

use Illuminate\Database\Seeder;

class SecurityPermissionSeeder extends Seeder
{
    use SeedsPermissions;

    public function run(): void
    {
        $permissions = [
            ['name' => 'Security Settings', 'slug' => 'security.settings', 'group_name' => 'Security'],
            ['name' => 'Login Holidays', 'slug' => 'login.holidays', 'group_name' => 'Security'],
            ['name' => 'User Devices', 'slug' => 'user.devices', 'group_name' => 'Security'],
            ['name' => 'Audit Logs', 'slug' => 'audit.logs', 'group_name' => 'Security'],
        ];

        $this->seedPermissions($permissions);
    }
}
