<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SuperAdminPermissionSeeder extends Seeder
{
    /**
     * Assign all permissions to super_admin/admin role safely.
     * This seeder supports common schemas:
     * - roles.slug or roles.name
     * - role_permissions.role_id + permission_id
     */
    public function run(): void
    {
        if (!Schema::hasTable('roles') || !Schema::hasTable('permissions') || !Schema::hasTable('role_permissions')) {
            return;
        }

        $roleQuery = Role::query();

        if (Schema::hasColumn('roles', 'slug')) {
            $roleQuery->whereIn('slug', ['super_admin', 'super-admin', 'admin']);
        } elseif (Schema::hasColumn('roles', 'name')) {
            $roleQuery->whereIn('name', ['Super Admin', 'Admin', 'super_admin', 'admin']);
        }

        $role = $roleQuery->first();

        if (!$role) {
            return;
        }

        $permissions = Permission::all(['id']);

        foreach ($permissions as $permission) {
            DB::table('role_permissions')->updateOrInsert(
                [
                    'role_id' => $role->id,
                    'permission_id' => $permission->id,
                ],
                [
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }
}
