<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RoleController extends Controller
{
    public function index()
    {
        return Role::with('permissions')
            ->latest()
            ->get();
    }

    public function show(Role $role)
    {
        return $role->load('permissions');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:roles,name'],
            'slug' => ['required', 'string', 'max:255', 'unique:roles,slug'],
            'bypass_device_restriction' => ['nullable', 'boolean'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['required', 'string', 'exists:permissions,slug'],
        ]);

        $role = Role::create([
            'name' => $data['name'],
            'slug' => $data['slug'],
            'bypass_device_restriction' => (bool) ($data['bypass_device_restriction'] ?? false),
            'is_active' => true,
        ]);

        $this->syncPermissionSlugs($role, $data['permissions'] ?? []);

        return response()->json([
            'message' => 'Role created successfully',
            'data' => $role->load('permissions'),
        ], 201);
    }

    public function update(Request $request, Role $role)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('roles', 'name')->ignore($role->id)],
            'slug' => ['required', 'string', 'max:255', Rule::unique('roles', 'slug')->ignore($role->id)],
            'bypass_device_restriction' => ['nullable', 'boolean'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['required', 'string', 'exists:permissions,slug'],
        ]);

        $role->update([
            'name' => $data['name'],
            'slug' => $data['slug'],
            'bypass_device_restriction' => (bool) ($data['bypass_device_restriction'] ?? false),
        ]);

        $this->syncPermissionSlugs($role, $data['permissions'] ?? []);

        return response()->json([
            'message' => 'Role updated successfully',
            'data' => $role->load('permissions'),
        ]);
    }

    public function permissions(Role $role)
    {
        return response()->json([
            'permissions' => $role->permissions()->pluck('slug')->values(),
        ]);
    }

    public function syncPermissions(Request $request, Role $role)
    {
        $data = $request->validate([
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['required', 'string', 'exists:permissions,slug'],
            'bypass_device_restriction' => ['nullable', 'boolean'],
        ]);

        if ($request->has('bypass_device_restriction')) {
            $role->update([
                'bypass_device_restriction' => (bool) $data['bypass_device_restriction'],
            ]);
        }

        $this->syncPermissionSlugs($role, $data['permissions'] ?? []);

        return response()->json([
            'message' => 'Role permissions updated successfully',
            'data' => $role->fresh()->load('permissions'),
        ]);
    }

    public function destroy(Role $role)
    {
        $role->delete();

        return response()->json([
            'message' => 'Role deleted successfully',
        ]);
    }

    private function syncPermissionSlugs(Role $role, array $permissionSlugs): void
    {
        $permissionIds = Permission::whereIn('slug', $permissionSlugs)
            ->pluck('id')
            ->toArray();

        $role->permissions()->sync($permissionIds);
    }
}
