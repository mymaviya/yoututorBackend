<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\Permission;
use Illuminate\Http\Request;

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
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:roles,name',
            'slug' => 'required|string|max:255|unique:roles,slug',
            'bypass_device_restriction' => 'boolean',
            'permissions' => 'nullable|array',
        ]);

        $role = Role::create([
            'name' => $validated['name'],
            'slug' => $validated['slug'],
            'bypass_device_restriction' => $validated['bypass_device_restriction'],
        ]);

        if ($request->permissions) {

            $permissionIds = Permission::whereIn(
                'slug',
                $request->permissions
            )->pluck('id');

            $role->permissions()->sync($permissionIds);
        }

        return response()->json([
            'message' => 'Role created successfully',
            'data' => $role->load('permissions'),
        ]);
    }

    public function update(Request $request, Role $role)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:roles,name,' . $role->id,
            'slug' => 'required|string|max:255|unique:roles,slug,' . $role->id,
            'bypass_device_restriction' => 'boolean',
            'permissions' => 'nullable|array',
        ]);

        $role->update([
            'name' => $validated['name'],
            'slug' => $validated['slug'],
            'bypass_device_restriction' => $validated['bypass_device_restriction'],
        ]);

        $permissionIds = Permission::whereIn(
            'slug',
            $request->permissions ?? []
        )->pluck('id');

        $role->permissions()->sync($permissionIds);

        return response()->json([
            'message' => 'Role updated successfully',
            'data' => $role->load('permissions'),
        ]);
    }

    public function permissions(Role $role)
    {
        return response()->json([
            'permissions' => $role->permissions()->pluck('slug'),
        ]);
    }

    public function syncPermissions(Request $request, Role $role)
    {
        $data = $request->validate([
            'permissions' => 'nullable|array',
            'bypass_device_restriction' => 'boolean',
        ]);

        $permissionIds = Permission::whereIn(
            'slug',
            $data['permissions'] ?? []
        )->pluck('id');

        $role->permissions()->sync($permissionIds);

        return response()->json([
            'message' => 'Role permissions updated successfully',
        ]);
    }

    public function destroy(Role $role)
    {
        $role->delete();

        return response()->json([
            'message' => 'Role deleted successfully',
        ]);
    }
}
