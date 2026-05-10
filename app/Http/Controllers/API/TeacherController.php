<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Teacher;
use App\Models\TeacherAssignment;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class TeacherController extends Controller
{
    public function index()
    {
        $teachers = Teacher::with([
            'user',
            'assignments.grade',
            'assignments.subject'
        ])
            ->latest()
            ->get();

        return response()->json($teachers);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string',
            'email' => 'required|email|unique:users,email',
            'contact' => 'required|numeric|digits:10',
            'qualification' => 'nullable|string',
            'designation' => 'nullable|string',

            'assignments' => 'nullable|array',
            'assignments.*.grade_id' => 'required|exists:grades,id',
            'assignments.*.subject_id' => 'required|exists:subjects,id',
        ]);

        DB::beginTransaction();

        $namePart = strtolower(substr(preg_replace('/\s+/', '', $data['name']), 0, 4));
        $mobileDigits = preg_replace('/\D/', '', $data['contact']);
        $mobilePart = substr($mobileDigits, 0, 6);

        $password = $namePart . $mobilePart;

        try {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'contact' => $data['contact'],
                'password' => Hash::make($password),
                'role' => 'teacher'
            ]);

            $teacher = Teacher::create([
                'user_id' => $user->id,
                'contact' => $data['contact'] ?? null,
                'qualification' => $data['qualification'] ?? null,
                'designation' => $data['designation'] ?? null,
                'is_active' => true
            ]);

            foreach ($data['assignments'] ?? [] as $item) {
                TeacherAssignment::create([
                    'teacher_id' => $teacher->id,
                    'grade_id' => $item['grade_id'],
                    'subject_id' => $item['subject_id'],
                ]);
            }

            DB::commit();

            return response()->json([
                'message' => 'Teacher created successfully',
                'default_password' => $password,
                'data' => $teacher->load(['user', 'grades.grade'])
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Teacher creation failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show($id)
    {
        return Teacher::with([
            'user',
            'grades.grade'
        ])->findOrFail($id);
    }

    public function update(Request $request, $id)
    {
        $teacher = Teacher::with('user')->findOrFail($id);

        $data = $request->validate([
            'name' => 'required|string',
            'email' => 'required|email|unique:users,email,' . $teacher->user_id,
            'contact' => 'required|numeric|digits:10',
            'qualification' => 'nullable|string',
            'designation' => 'nullable|string',
            'is_active' => 'boolean',

            'assignments' => 'required|array|min:1',
            'assignments.*.grade_id' => 'required|exists:grades,id',
            'assignments.*.subject_id' => 'required|exists:subjects,id',
        ]);

        DB::beginTransaction();

        try {
            $teacher->user->update([
                'name' => $data['name'],
                'email' => $data['email'],
            ]);

            $teacher->update([
                'contact' => $data['contact'],
                'qualification' => $data['qualification'] ?? null,
                'designation' => $data['designation'] ?? null,
                'is_active' => $data['is_active'] ?? true,
            ]);

            // Delete old assignments
            $teacher->assignments()->delete();

            // Create new assignments
            foreach ($data['assignments'] as $item) {
                TeacherAssignment::create([
                    'teacher_id' => $teacher->id,
                    'grade_id' => $item['grade_id'],
                    'subject_id' => $item['subject_id'],
                ]);
            }

            DB::commit();

            return response()->json([
                'message' => 'Teacher updated successfully',
                'data' => $teacher->fresh()->load([
                    'user',
                    'assignments.grade',
                    'assignments.subject'
                ])
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Teacher update failed',
                'error' => $e->getMessage()
            ], 500);
        }


    }

    public function destroy($id)
    {
        $teacher = Teacher::findOrFail($id);

        $teacher->user()->delete();

        return response()->json([
            'message' => 'Teacher deleted successfully'
        ]);
    }
}
