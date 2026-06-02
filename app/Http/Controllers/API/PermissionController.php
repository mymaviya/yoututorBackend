<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PermissionController extends Controller
{
    public function index()
    {
        return Permission::orderBy('name')->get();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
            ],
            'slug' => [
                'required',
                'string',
                'max:255',
                'regex:/^[a-z0-9_]+$/',
                'unique:permissions,slug',
            ],
            'group_name' => 'nullable|string|max:255',
        ]);

        $permission = Permission::create($data);

        return response()->json([
            'message' => 'Permission created successfully.',
            'data' => $permission,
        ], 201);
    }

    public function show(Permission $permission)
    {
        return $permission;
    }

    public function update(Request $request, Permission $permission)
    {
        $data = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
            ],
            'slug' => [
                'required',
                'string',
                'max:255',
                'regex:/^[a-z0-9_]+$/',
                Rule::unique('permissions', 'slug')->ignore($permission->id),
            ],
            'group_name' => 'nullable|string|max:255',
        ]);

        $permission->update($data);

        return response()->json([
            'message' => 'Permission updated successfully.',
            'data' => $permission,
        ]);
    }

    public function destroy(Permission $permission)
    {
        if ($permission->roles()->exists() || $permission->users()->exists()) {
            return response()->json([
                'message' => 'This permission is assigned to role/user and cannot be deleted.',
            ], 422);
        }

        $permission->delete();

        return response()->json([
            'message' => 'Permission deleted successfully.',
        ]);
    }
}
