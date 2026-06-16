<?php

namespace Database\Seeders\Permissions;

use Illuminate\Database\Seeder;

class AdministrationPermissionSeeder extends Seeder
{
    use SeedsPermissions;

    public function run(): void
    {
        $permissions = [
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
            ['name' => 'Manage Permissions', 'slug' => 'manage.permissions', 'group_name' => 'Administration'],
        ];

        $this->seedPermissions($permissions);
    }
}
