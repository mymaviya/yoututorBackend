<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Permission;
use App\Models\Subscription;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use App\Services\AuditService;

class UserController extends Controller
{
    private function resolveSubscriptionId(array $data): ?int
    {
        $authUser = auth()->user();

        if (!$authUser) {
            return $data['subscription_id'] ?? null;
        }

        $role = $authUser->roleData?->slug ?? $authUser->role;

        if (in_array($role, ['superadmin', 'super_admin'], true)) {
            return $data['subscription_id'] ?? null;
        }

        return $authUser->subscription_id;
    }

    public function index()
    {
        $authUser = auth()->user();
        $userRole = $authUser->roleData?->slug ?? $authUser->role;

        $query = User::with(['role', 'subscription', 'teacherProfile'])
            ->select([
                'id',
                'subscription_id',
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
            ]);

        if (!in_array($userRole, ['superadmin', 'super_admin'])) {
            $query->where('subscription_id', $authUser->subscription_id);
        }

        return $query->orderBy('name')->get();
    }

    public function show(User $user)
    {
        if ($response = $this->ensureUserAccess($user)) {
            return $response;
        }

        return $user->load('role', 'permissions', 'subscription', 'teacherProfile');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:6',

            'contact' => 'nullable|string|max:20',
            'address' => 'nullable|string',

            'role' => 'nullable|string|max:255',
            'role_id' => 'nullable|exists:roles,id',
            'subscription_id' => 'nullable|exists:subscriptions,id',

            'is_active' => 'nullable|boolean',
            'login_enabled' => 'nullable|boolean',

            'login_start_date' => 'nullable|date',
            'login_end_date' => 'nullable|date|after_or_equal:login_start_date',

            'daily_login_start_time' => 'nullable|date_format:H:i',
            'daily_login_end_time' => 'nullable|date_format:H:i|after:daily_login_start_time',

            'employee_code' => 'nullable|string|max:100',
            'designation' => 'nullable|string|max:255',
            'qualification' => 'nullable|string|max:255',
            'joining_date' => 'nullable|date',
            'experience_years' => 'nullable|integer|min:0',
            'bio' => 'nullable|string',
        ]);

        $subscriptionId = $this->resolveSubscriptionId($data);

        if ($subscriptionId) {
            $subscription = Subscription::find($subscriptionId);

            if ($subscription && $subscription->max_users) {
                $currentUsers = User::where('subscription_id', $subscriptionId)->count();

                if ($currentUsers >= $subscription->max_users) {
                    return response()->json([
                        'message' => 'User limit reached for this subscription plan.',
                        'errors' => [
                            'subscription_id' => [
                                'This subscription allows only ' . $subscription->max_users . ' users.'
                            ],
                        ],
                    ], 422);
                }
            }
        }

        $user = User::create([
            'subscription_id' => $subscriptionId,

            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),

            'contact' => $data['contact'] ?? null,
            'address' => $data['address'] ?? null,

            'role' => $data['role'] ?? 'user',
            'role_id' => $data['role_id'] ?? null,

            'is_active' => $data['is_active'] ?? true,
            'login_enabled' => $data['login_enabled'] ?? true,

            'login_start_date' => $data['login_start_date'] ?? now()->toDateString(),
            'login_end_date' => $data['login_end_date'] ?? null,

            'daily_login_start_time' => $data['daily_login_start_time'] ?? null,
            'daily_login_end_time' => $data['daily_login_end_time'] ?? null,
        ]);

        if (($data['role'] ?? null) === 'teacher') {
            $user->teacherProfile()->create([
                'employee_code' => $data['employee_code'] ?? null,
                'designation' => $data['designation'] ?? null,
                'qualification' => $data['qualification'] ?? null,
                'joining_date' => $data['joining_date'] ?? null,
                'experience_years' => $data['experience_years'] ?? null,
                'bio' => $data['bio'] ?? null,
            ]);
        }

        AuditService::log('Users', 'Create', 'User created ID: ' . $user->id, null, $user->toArray());

        return response()->json([
            'message' => 'User created successfully',
            'data' => $user->load('role', 'subscription', 'teacherProfile'),
        ], 201);
    }

    public function update(Request $request, User $user)
    {
        if ($response = $this->ensureUserAccess($user)) {
            return $response;
        }

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

            'role' => 'nullable|string|max:255',
            'role_id' => 'nullable|exists:roles,id',
            'subscription_id' => 'nullable|exists:subscriptions,id',

            'is_active' => 'nullable|boolean',
            'login_enabled' => 'nullable|boolean',

            'login_start_date' => 'nullable|date',
            'login_end_date' => 'nullable|date|after_or_equal:login_start_date',

            'daily_login_start_time' => 'nullable|date_format:H:i',
            'daily_login_end_time' => 'nullable|date_format:H:i|after:daily_login_start_time',

            'employee_code' => 'nullable|string|max:100',
            'designation' => 'nullable|string|max:255',
            'qualification' => 'nullable|string|max:255',
            'joining_date' => 'nullable|date',
            'experience_years' => 'nullable|integer|min:0',
            'bio' => 'nullable|string',
        ]);

        $payload = [
            'name' => $data['name'],
            'email' => $data['email'],

            'contact' => $data['contact'] ?? null,
            'address' => $data['address'] ?? null,

            'role' => $data['role'] ?? $user->role,
            'role_id' => $data['role_id'] ?? null,

            'is_active' => $data['is_active'] ?? true,
            'login_enabled' => $data['login_enabled'] ?? true,

            'login_start_date' => $data['login_start_date'] ?? now()->toDateString(),
            'login_end_date' => $data['login_end_date'] ?? null,

            'daily_login_start_time' => $data['daily_login_start_time'] ?? null,
            'daily_login_end_time' => $data['daily_login_end_time'] ?? null,
        ];

        $authRole = auth()->user()?->roleData?->slug ?? auth()->user()?->role;

        if (in_array($authRole, ['superadmin', 'super_admin'], true)) {
            $payload['subscription_id'] = $data['subscription_id'] ?? $user->subscription_id;
        } elseif (!$user->subscription_id) {
            $payload['subscription_id'] = auth()->user()?->subscription_id;
        }

        if (!empty($data['password'])) {
            $payload['password'] = Hash::make($data['password']);
        }

        $user->update($payload);

        if (($payload['role'] ?? $user->role) === 'teacher') {
            $user->teacherProfile()->updateOrCreate(
                ['user_id' => $user->id],
                [
                    'employee_code' => $data['employee_code'] ?? null,
                    'designation' => $data['designation'] ?? null,
                    'qualification' => $data['qualification'] ?? null,
                    'joining_date' => $data['joining_date'] ?? null,
                    'experience_years' => $data['experience_years'] ?? null,
                    'bio' => $data['bio'] ?? null,
                ]
            );
        }

        AuditService::log('Users', 'Update', 'User updated ID: ' . $user->id, null, $user->toArray());

        return response()->json([
            'message' => 'User updated successfully',
            'data' => $user->load('role', 'subscription', 'teacherProfile'),
        ]);
    }

    public function destroy(User $user)
    {
        if ($response = $this->ensureUserAccess($user)) {
            return $response;
        }
        
        $user->delete();

        AuditService::log('Users', 'Delete', 'User deleted ID: ' . $user->id, $user->toArray(), null);

        return response()->json([
            'message' => 'User deleted successfully',
        ]);
    }

    public function permissions(User $user)
    {
        if ($response = $this->ensureUserAccess($user)) {
            return $response;
        }

        return response()->json([
            'permissions' => $user->permissions()
                ->wherePivot('allowed', true)
                ->pluck('slug'),
        ]);
    }

    public function syncPermissions(Request $request, User $user)
    {
        if ($response = $this->ensureUserAccess($user)) {
            return $response;
        }

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

        $query = User::whereIn('id', $data['user_ids']);

        $authUser = auth()->user();
        $userRole = $authUser->roleData?->slug ?? $authUser->role;

        if (! in_array($userRole, ['superadmin', 'super_admin'], true)) {
            $query->where('subscription_id', $authUser->subscription_id);
        }

        $query->update([
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

    private function ensureUserAccess(User $targetUser)
    {
        $authUser = auth()->user();
        $userRole = $authUser->roleData?->slug ?? $authUser->role;

        if (in_array($userRole, ['superadmin', 'super_admin'])) {
            return null;
        }

        if ($targetUser->subscription_id !== $authUser->subscription_id) {
            return response()->json([
                'message' => 'You are not allowed to access this user.',
            ], 403);
        }

        return null;
    }
}
