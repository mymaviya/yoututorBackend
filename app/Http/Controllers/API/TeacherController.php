<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\TeacherAssignment;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Imports\TeachersImport;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\TeacherImportTemplateExport;


class TeacherController extends Controller
{
    private function teacherQuery()
    {
        return User::query()
            ->where(function ($q) {
                $q->where('role', 'teacher')
                    ->orWhereHas('roleData', fn($role) => $role->where('slug', 'teacher'));
            });
    }

    public function index()
    {
        return response()->json(
            $this->teacherQuery()
                ->with(['roleData', 'teacherAssignments.grade', 'teacherAssignments.stream', 'teacherAssignments.subject'])
                ->latest()
                ->get()
        );
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'contact' => 'nullable|string|max:20',
            'address' => 'nullable|string',
            'is_active' => 'boolean',
            'assignments' => 'nullable|array',
            'assignments.*.grade_id' => 'required|exists:grades,id',
            'assignments.*.stream_id' => 'nullable|exists:streams,id',
            'assignments.*.subject_id' => 'required|exists:subjects,id',
        ]);

        return DB::transaction(function () use ($data) {
            $roleId = Role::where('slug', 'teacher')->value('id');

            $namePart = strtolower(substr(preg_replace('/\s+/', '', $data['name']), 0, 4));
            $mobileDigits = preg_replace('/\D/', '', $data['contact'] ?? '000000');
            $password = $namePart . substr($mobileDigits, 0, 6);

            $teacher = User::create([
                'role_id' => $roleId,
                'role' => 'teacher',
                'name' => $data['name'],
                'email' => $data['email'],
                'contact' => $data['contact'] ?? null,
                'address' => $data['address'] ?? null,
                'password' => Hash::make($password),
                'is_active' => $data['is_active'] ?? true,
                'login_enabled' => true,
                'password_change_required' => true,
            ]);

            foreach ($data['assignments'] ?? [] as $item) {
                TeacherAssignment::updateOrCreate([
                    'teacher_id' => $teacher->id,
                    'grade_id' => $item['grade_id'],
                    'stream_id' => $item['stream_id'] ?? null,
                    'subject_id' => $item['subject_id'],
                ], ['is_active' => true]);
            }

            return response()->json([
                'message' => 'Teacher created successfully',
                'default_password' => $password,
                'data' => $teacher->load(['roleData', 'teacherAssignments.grade', 'teacherAssignments.stream', 'teacherAssignments.subject']),
            ], 201);
        });
    }

    public function show($id)
    {
        return $this->teacherQuery()
            ->with(['roleData', 'teacherAssignments.grade', 'teacherAssignments.stream', 'teacherAssignments.subject'])
            ->findOrFail($id);
    }

    public function update(Request $request, $id)
    {
        $teacher = $this->teacherQuery()->findOrFail($id);

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $teacher->id,
            'contact' => 'nullable|string|max:20',
            'address' => 'nullable|string',
            'is_active' => 'boolean',
            'assignments' => 'nullable|array',
            'assignments.*.grade_id' => 'required|exists:grades,id',
            'assignments.*.stream_id' => 'nullable|exists:streams,id',
            'assignments.*.subject_id' => 'required|exists:subjects,id',
        ]);

        return DB::transaction(function () use ($teacher, $data) {
            $teacher->update([
                'name' => $data['name'],
                'email' => $data['email'],
                'contact' => $data['contact'] ?? null,
                'address' => $data['address'] ?? null,
                'is_active' => $data['is_active'] ?? true,
            ]);

            if (array_key_exists('assignments', $data)) {
                $teacher->teacherAssignments()->delete();

                foreach ($data['assignments'] ?? [] as $item) {
                    TeacherAssignment::create([
                        'teacher_id' => $teacher->id,
                        'grade_id' => $item['grade_id'],
                        'stream_id' => $item['stream_id'] ?? null,
                        'subject_id' => $item['subject_id'],
                        'is_active' => true,
                    ]);
                }
            }

            return response()->json([
                'message' => 'Teacher updated successfully',
                'data' => $teacher->fresh()->load(['roleData', 'teacherAssignments.grade', 'teacherAssignments.stream', 'teacherAssignments.subject']),
            ]);
        });
    }

    public function destroy($id)
    {
        $teacher = $this->teacherQuery()->findOrFail($id);
        $teacher->delete();

        return response()->json(['message' => 'Teacher deleted successfully']);
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv',
        ]);

        $import = new TeachersImport();

        Excel::import($import, $request->file('file'));

        return response()->json([
            'message' => 'Teacher import completed',
            'imported' => $import->imported,
            'skipped' => $import->skipped,
            'errors' => $import->errors,
            'credentials' => $import->credentials,
        ]);
    }

    public function importPreview(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv',
        ]);

        $rows = Excel::toArray([], $request->file('file'))[0] ?? [];

        $previewRows = collect($rows)
            ->skip(1)
            ->take(10)
            ->map(function ($row) {
                return [
                    'name' => $row[0] ?? '',
                    'email' => $row[1] ?? '',
                    'mobile' => $row[2] ?? '',
                    'qualification' => $row[3] ?? '',
                    'address' => $row[4] ?? '',
                ];
            })
            ->values();

        return response()->json([
            'rows' => $previewRows,
            'total_rows' => max(count($rows) - 1, 0),
        ]);
    }

    public function downloadTemplate()
    {
        return Excel::download(
            new TeacherImportTemplateExport(),
            'teacher_import_template.xlsx'
        );
    }
}
