<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\Permission;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AdminRolePermissionSeeder extends Seeder
{
    public function run(): void
    {

        $adminRole = Role::firstOrCreate(
            ['name' => 'Admin'],
            ['slug' => 'admin']
        );

        $permissionIds = Permission::pluck('id')->toArray();

        DB::table('role_permissions')
            ->where('role_id', $adminRole->id)
            ->delete();

        $data = collect($permissionIds)->map(fn ($permissionId) => [
            'role_id' => $adminRole->id,
            'permission_id' => $permissionId,
            'created_at' => now(),
            'updated_at' => now(),
        ])->toArray();

        DB::table('role_permissions')->insert($data);
    }
}
