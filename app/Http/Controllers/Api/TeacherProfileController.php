<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TeacherProfile;
use App\Models\User;
use Illuminate\Http\Request;

class TeacherProfileController extends Controller
{
    private function isSuperAdmin(): bool
    {
        $user = auth()->user();
        $role = $user?->roleData?->slug ?? $user?->role;

        return in_array($role, ['superadmin', 'super_admin'], true);
    }

    private function ensureTeacherAccess(User $teacher)
    {
        if ($this->isSuperAdmin()) {
            return null;
        }

        if ((int) $teacher->subscription_id !== (int) auth()->user()?->subscription_id) {
            return response()->json([
                'message' => 'You are not allowed to access this teacher profile.',
            ], 403);
        }

        return null;
    }

    public function show(User $teacher)
    {
        if ($response = $this->ensureTeacherAccess($teacher)) {
            return $response;
        }

        return response()->json([
            'data' => $teacher->load([
                'roleData',
                'teacherProfile',
                'teacherAssignments.grade',
                'teacherAssignments.stream',
                'teacherAssignments.subject',
            ]),
        ]);
    }

    public function update(Request $request, User $teacher)
    {
        if ($response = $this->ensureTeacherAccess($teacher)) {
            return $response;
        }

        $data = $request->validate([
            'employee_code' => 'nullable|string|max:100',
            'designation' => 'nullable|string|max:255',
            'qualification' => 'nullable|string|max:255',
            'joining_date' => 'nullable|date',
            'experience_years' => 'nullable|integer|min:0|max:60',
            'bio' => 'nullable|string|max:5000',
        ]);

        $profile = TeacherProfile::updateOrCreate(
            [
                'user_id' => $teacher->id,
            ],
            [
                'subscription_id' => $teacher->subscription_id,
                'employee_code' => $data['employee_code'] ?? null,
                'designation' => $data['designation'] ?? null,
                'qualification' => $data['qualification'] ?? null,
                'joining_date' => $data['joining_date'] ?? null,
                'experience_years' => $data['experience_years'] ?? null,
                'bio' => $data['bio'] ?? null,
            ]
        );

        return response()->json([
            'message' => 'Teacher profile updated successfully.',
            'data' => $profile,
        ]);
    }
}