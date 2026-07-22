<?php

namespace App\Http\Controllers\Api;

use App\Exports\TeacherImportTemplateExport;
use App\Http\Controllers\Controller;
use App\Imports\TeachersImport;
use App\Models\Role;
use App\Models\TeacherAssignment;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;

class TeacherController extends Controller
{
    private function isSuperAdmin(): bool
    {
        $user = auth()->user();
        $role = $user?->roleData?->slug ?? $user?->role;

        return in_array($role, ['superadmin', 'super_admin'], true);
    }

    private function tenantId(): ?int
    {
        return auth()->user()?->subscription_id;
    }

    private function teacherQuery()
    {
        $query = User::query()
            ->where(function ($q) {
                $q->where('role', 'teacher')
                    ->orWhereHas('roleData', fn ($role) => $role->where('slug', 'teacher'));
            });

        if (! $this->isSuperAdmin()) {
            $query->where('subscription_id', $this->tenantId());
        }

        return $query;
    }

    private function assignmentRelations(): array
    {
        return [
            'roleData',
            'teacherAssignments.academicYear',
            'teacherAssignments.grade',
            'teacherAssignments.section',
            'teacherAssignments.stream',
            'teacherAssignments.subject',
        ];
    }

    private function checkUserLimit(): ?\Illuminate\Http\JsonResponse
    {
        $subscription = auth()->user()?->subscription;

        if (! $subscription || ! $subscription->max_users) {
            return null;
        }

        $currentUsers = User::where('subscription_id', $subscription->id)->count();

        if ($currentUsers >= $subscription->max_users) {
            return response()->json([
                'message' => 'User limit reached for this subscription plan.',
                'errors' => [
                    'subscription' => [
                        'This subscription allows only ' . $subscription->max_users . ' users.',
                    ],
                ],
            ], 422);
        }

        return null;
    }

    private function assignmentRules(): array
    {
        $subscriptionId = $this->tenantId();

        return [
            'assignments' => ['nullable', 'array'],
            'assignments.*.academic_year_id' => [
                'required',
                'integer',
                Rule::exists('academic_years', 'id')->where(
                    fn ($query) => $query->where('subscription_id', $subscriptionId)
                ),
            ],
            'assignments.*.grade_id' => ['required', 'integer', 'exists:grades,id'],
            'assignments.*.section_id' => ['nullable', 'integer', 'exists:sections,id'],
            'assignments.*.stream_id' => ['nullable', 'integer', 'exists:streams,id'],
            'assignments.*.subject_id' => ['required', 'integer', 'exists:subjects,id'],
        ];
    }

    private function validateAssignmentsBelongToTenant(array $assignments): ?\Illuminate\Http\JsonResponse
    {
        if ($this->isSuperAdmin()) {
            return null;
        }

        foreach ($assignments as $item) {
            $subjectExists = \App\Models\Subject::where('id', $item['subject_id'])
                ->where('grade_id', $item['grade_id'])
                ->where(function ($q) use ($item) {
                    $q->whereNull('stream_id');

                    if (! empty($item['stream_id'])) {
                        $q->orWhere('stream_id', $item['stream_id']);
                    }
                })
                ->exists();

            if (! $subjectExists) {
                return response()->json([
                    'message' => 'Selected assignment does not belong to the selected grade/stream/subject.',
                ], 422);
            }

            if (! empty($item['section_id'])) {
                $sectionMatchesGrade = \App\Models\Section::whereKey($item['section_id'])
                    ->where('grade_id', $item['grade_id'])
                    ->exists();

                if (! $sectionMatchesGrade) {
                    return response()->json([
                        'message' => 'Selected section does not belong to the selected grade.',
                        'errors' => [
                            'assignments' => ['Selected section does not belong to the selected grade.'],
                        ],
                    ], 422);
                }
            }
        }

        return null;
    }

    private function assignmentIdentity(User $teacher, array $item): array
    {
        return [
            'subscription_id' => $teacher->subscription_id,
            'academic_year_id' => $item['academic_year_id'],
            'teacher_id' => $teacher->id,
            'grade_id' => $item['grade_id'],
            'section_id' => $item['section_id'] ?? null,
            'stream_id' => $item['stream_id'] ?? null,
            'subject_id' => $item['subject_id'],
        ];
    }

    public function index()
    {
        return response()->json(
            $this->teacherQuery()
                ->with($this->assignmentRelations())
                ->latest()
                ->get()
        );
    }

    public function store(Request $request)
    {
        $data = $request->validate(array_merge([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'contact' => 'nullable|string|max:20',
            'address' => 'nullable|string',
            'qualification' => 'nullable|string|max:255',
            'designation' => 'nullable|string|max:255',
            'is_active' => 'boolean',
        ], $this->assignmentRules()));

        if ($response = $this->checkUserLimit()) {
            return $response;
        }

        if ($response = $this->validateAssignmentsBelongToTenant($data['assignments'] ?? [])) {
            return $response;
        }

        return DB::transaction(function () use ($data) {
            $roleId = Role::where('slug', 'teacher')->value('id');

            $namePart = strtolower(substr(preg_replace('/\s+/', '', $data['name']), 0, 4));
            $mobileDigits = preg_replace('/\D/', '', $data['contact'] ?? '000000');
            $password = $namePart . substr($mobileDigits, 0, 6);

            $teacher = User::create([
                'subscription_id' => $this->tenantId(),
                'role_id' => $roleId,
                'role' => 'teacher',
                'name' => $data['name'],
                'email' => $data['email'],
                'contact' => $data['contact'] ?? null,
                'address' => $data['address'] ?? null,
                'qualification' => $data['qualification'] ?? null,
                'designation' => $data['designation'] ?? null,
                'password' => Hash::make($password),
                'is_active' => $data['is_active'] ?? true,
                'login_enabled' => true,
                'password_change_required' => true,
            ]);

            foreach ($data['assignments'] ?? [] as $item) {
                TeacherAssignment::updateOrCreate(
                    $this->assignmentIdentity($teacher, $item),
                    ['is_active' => true]
                );
            }

            return response()->json([
                'message' => 'Teacher created successfully',
                'default_password' => $password,
                'data' => $teacher->load($this->assignmentRelations()),
            ], 201);
        });
    }

    public function show($id)
    {
        return $this->teacherQuery()
            ->with($this->assignmentRelations())
            ->findOrFail($id);
    }

    public function update(Request $request, $id)
    {
        $teacher = $this->teacherQuery()->findOrFail($id);

        $data = $request->validate(array_merge([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $teacher->id,
            'contact' => 'nullable|string|max:20',
            'address' => 'nullable|string',
            'qualification' => 'nullable|string|max:255',
            'designation' => 'nullable|string|max:255',
            'is_active' => 'boolean',
        ], $this->assignmentRules()));

        if ($response = $this->validateAssignmentsBelongToTenant($data['assignments'] ?? [])) {
            return $response;
        }

        return DB::transaction(function () use ($teacher, $data) {
            $teacher->update([
                'name' => $data['name'],
                'email' => $data['email'],
                'contact' => $data['contact'] ?? null,
                'address' => $data['address'] ?? null,
                'qualification' => $data['qualification'] ?? null,
                'designation' => $data['designation'] ?? null,
                'is_active' => $data['is_active'] ?? true,
            ]);

            if (array_key_exists('assignments', $data)) {
                $teacher->teacherAssignments()->delete();

                foreach ($data['assignments'] ?? [] as $item) {
                    TeacherAssignment::create([
                        ...$this->assignmentIdentity($teacher, $item),
                        'is_active' => true,
                    ]);
                }
            }

            return response()->json([
                'message' => 'Teacher updated successfully',
                'data' => $teacher->fresh()->load($this->assignmentRelations()),
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

        $import = new TeachersImport($this->tenantId());
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
