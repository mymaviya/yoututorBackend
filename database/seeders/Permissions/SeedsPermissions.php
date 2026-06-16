<?php

namespace Database\Seeders\Permissions;

use App\Models\Permission;
use Illuminate\Support\Facades\Schema;

trait SeedsPermissions
{
    protected function seedPermissions(array $permissions): void
    {
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

            Permission::updateOrCreate(
                ['slug' => $permission['slug']],
                $payload
            );
        }
    }
}
