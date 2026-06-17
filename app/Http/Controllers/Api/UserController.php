<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Permission;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use App\Services\AuditService;

class UserController extends Controller
{
    public function index()
    {
        return User::with('role')
            ->select([
                'id',
                'name',
                'email',
                'contact',
                'address',
                'role',
                'role_id',
                'is_active',
                'login_enabled',
                'login_start_date',
                'login_end_date',
                'daily_login_start_time',
                'daily_login_end_time',
            ])
            ->orderBy('name')
            ->get();
    }

    public function show(User $user)
    {
        return $user->load('role', 'permissions');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:6',

            'contact' => 'nullable|string|max:20',
            'address' => 'nullable|string',

            'role_id' => 'nullable|exists:roles,id',

            'is_active' => 'nullable|boolean',
            'login_enabled' => 'nullable|boolean',

            'login_start_date' => 'nullable|date',
            'login_end_date' => 'nullable|date|after_or_equal:login_start_date',

            'daily_login_start_time' => 'nullable|date_format:H:i',
            'daily_login_end_time' => 'nullable|date_format:H:i|after:daily_login_start_time',
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),

            'contact' => $data['contact'] ?? null,
            'address' => $data['address'] ?? null,

            'role_id' => $data['role_id'] ?? null,

            'is_active' => $data['is_active'] ?? true,
            'login_enabled' => $data['login_enabled'] ?? true,

            'login_start_date' => $data['login_start_date'] ?? now()->toDateString(),
            'login_end_date' => $data['login_end_date'] ?? null,

            'daily_login_start_time' => $data['daily_login_start_time'] ?? null,
            'daily_login_end_time' => $data['daily_login_end_time'] ?? null,
        ]);

        AuditService::log('Users','Create','User created ID: ' . $user->id, null, $user->toArray());

        return response()->json([
            'message' => 'User created successfully',
            'data' => $user->load('role'),
        ], 201);
    }

    public function update(Request $request, User $user)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',

            'email' => [
                'required',
                'email',
                Rule::unique('users', 'email')->ignore($user->id),
            ],

            'password' => 'nullable|string|min:6',

            'contact' => 'nullable|string|max:20',
            'address' => 'nullable|string',

            'role_id' => 'nullable|exists:roles,id',

            'is_active' => 'nullable|boolean',
            'login_enabled' => 'nullable|boolean',

            'login_start_date' => 'nullable|date',
            'login_end_date' => 'nullable|date|after_or_equal:login_start_date',

            'daily_login_start_time' => 'nullable|date_format:H:i',
            'daily_login_end_time' => 'nullable|date_format:H:i|after:daily_login_start_time',
        ]);

        $payload = [
            'name' => $data['name'],
            'email' => $data['email'],

            'contact' => $data['contact'] ?? null,
            'address' => $data['address'] ?? null,

            'role_id' => $data['role_id'] ?? null,

            'is_active' => $data['is_active'] ?? true,
            'login_enabled' => $data['login_enabled'] ?? true,

            'login_start_date' => $data['login_start_date'] ?? now()->toDateString(),
            'login_end_date' => $data['login_end_date'] ?? null,

            'daily_login_start_time' => $data['daily_login_start_time'] ?? null,
            'daily_login_end_time' => $data['daily_login_end_time'] ?? null,
        ];

        if (!empty($data['password'])) {
            $payload['password'] = Hash::make($data['password']);
        }

        $user->update($payload);

        AuditService::log('Users','Update','User updated ID: ' . $user->id, null, $user->toArray());

        return response()->json([
            'message' => 'User updated successfully',
            'data' => $user->load('role'),
        ]);
    }

    public function destroy(User $user)
    {
        $user->delete();

        AuditService::log('Users','Delete','User deleted ID: ' . $user->id, $user->toArray(), null);

        return response()->json([
            'message' => 'User deleted successfully',
        ]);
    }

    public function permissions(User $user)
    {
        return response()->json([
            'permissions' => $user->permissions()
                ->wherePivot('allowed', true)
                ->pluck('slug'),
        ]);
    }

    public function syncPermissions(Request $request, User $user)
    {
        $data = $request->validate([
            'permissions' => 'nullable|array',
        ]);

        $permissionIds = Permission::whereIn(
            'slug',
            $data['permissions'] ?? []
        )->pluck('id');

        $syncData = [];

        foreach ($permissionIds as $permissionId) {
            $syncData[$permissionId] = [
                'allowed' => true,
            ];
        }

        $user->permissions()->sync($syncData);

        return response()->json([
            'message' => 'User permissions updated successfully',
        ]);
    }

    public function bulkLoginAccess(Request $request)
    {
        $data = $request->validate([
            'user_ids' => 'required|array|min:1',
            'user_ids.*' => 'exists:users,id',

            'login_enabled' => 'required|boolean',
            'login_start_date' => 'nullable|date',
            'login_end_date' => 'nullable|date|after_or_equal:login_start_date',

            'daily_login_start_time' => 'nullable|date_format:H:i',
            'daily_login_end_time' => 'nullable|date_format:H:i|after:daily_login_start_time',
        ]);

        User::whereIn('id', $data['user_ids'])->update([
            'login_enabled' => $data['login_enabled'],
            'login_start_date' => $data['login_start_date'] ?? now()->toDateString(),
            'login_end_date' => $data['login_end_date'] ?? null,
            'daily_login_start_time' => $data['daily_login_start_time'] ?? null,
            'daily_login_end_time' => $data['daily_login_end_time'] ?? null,
        ]);

        return response()->json([
            'message' => 'Login access updated successfully',
        ]);
    }

}
