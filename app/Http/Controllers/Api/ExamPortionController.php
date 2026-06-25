<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ExamPortion;
use App\Models\Lesson;
use App\Models\User;
use Illuminate\Http\Request;

class ExamPortionController extends Controller
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

    private function ensurePortionAccess(ExamPortion $portion)
    {
        if ($this->isSuperAdmin()) {
            return null;
        }

        if ((int) $portion->subscription_id !== (int) $this->tenantId()) {
            return response()->json([
                'message' => 'You are not allowed to access this exam portion.',
            ], 403);
        }

        return null;
    }

    private function ensureTeacherBelongsToTenant(int $teacherId)
    {
        if ($this->isSuperAdmin()) {
            return null;
        }

        $exists = User::where('id', $teacherId)
            ->where('subscription_id', $this->tenantId())
            ->exists();

        if (! $exists) {
            return response()->json([
                'message' => 'Selected teacher does not belong to your subscription.',
            ], 422);
        }

        return null;
    }

    public function index(Request $request)
    {
        $query = ExamPortion::with([
            'teacher',
            'grade',
            'subject',
            'examName',
            'lessons.lesson',
            'assignedBy',
            'approvedBy',
        ])->latest();

        if (! $this->isSuperAdmin()) {
            $query->where('subscription_id', $this->tenantId());
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('grade_id')) {
            $query->where('grade_id', $request->grade_id);
        }

        if ($request->filled('subject_id')) {
            $query->where('subject_id', $request->subject_id);
        }

        if ($request->filled('teacher_id')) {
            $query->where('teacher_id', $request->teacher_id);
        }

        return response()->json($query->paginate(20));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'teacher_id' => 'required|exists:users,id',
            'grade_id' => 'required|exists:grades,id',
            'subject_id' => 'required|exists:subjects,id',
            'exam_name_ids' => 'required|array|min:1',
            'exam_name_ids.*' => 'exists:exam_names,id',
            'due_date' => 'nullable|date',
        ]);

        if ($response = $this->ensureTeacherBelongsToTenant((int) $data['teacher_id'])) {
            return $response;
        }

        $created = [];
        $duplicates = [];

        foreach ($data['exam_name_ids'] as $examNameId) {
            $exists = ExamPortion::where('subscription_id', $this->tenantId())
                ->where('grade_id', $data['grade_id'])
                ->where('subject_id', $data['subject_id'])
                ->where('exam_name_id', $examNameId)
                ->exists();

            if ($exists) {
                $duplicates[] = $examNameId;
            }
        }

        if (! empty($duplicates)) {
            $examNames = \App\Models\ExamName::whereIn('id', $duplicates)
                ->pluck('name')
                ->implode(', ');

            return response()->json([
                'message' => 'Portion already exists for: ' . $examNames,
            ], 422);
        }

        foreach ($data['exam_name_ids'] as $examNameId) {
            $portion = ExamPortion::create([
                'subscription_id' => $this->tenantId(),
                'teacher_id' => $data['teacher_id'],
                'grade_id' => $data['grade_id'],
                'subject_id' => $data['subject_id'],
                'exam_name_id' => $examNameId,
                'due_date' => $data['due_date'] ?? null,
                'status' => 'assigned',
                'assigned_by' => auth()->id(),
            ]);

            $portion->load(['teacher', 'grade', 'subject', 'examName']);

            notifyUser(
                $portion->teacher_id,
                'Exam Portion Assigned',
                'You have been assigned to prepare syllabus for ' . $portion->examName->name,
                'exam_portion',
                '/my-exam-portions'
            );

            $created[] = $portion;
        }

        return response()->json([
            'message' => 'Exam portions assigned successfully',
            'data' => $created,
        ], 201);
    }

    public function show($id)
    {
        $portion = ExamPortion::with([
            'teacher',
            'grade',
            'subject',
            'examName',
            'lessons.lesson',
            'assignedBy',
            'approvedBy',
        ])->findOrFail($id);

        if ($response = $this->ensurePortionAccess($portion)) {
            return $response;
        }

        return response()->json($portion);
    }

    public function update(Request $request, $id)
    {
        $portion = ExamPortion::findOrFail($id);

        if ($response = $this->ensurePortionAccess($portion)) {
            return $response;
        }

        $data = $request->validate([
            'teacher_id' => 'required|exists:users,id',
            'grade_id' => 'required|exists:grades,id',
            'subject_id' => 'required|exists:subjects,id',
            'exam_name_ids' => 'required|array|min:1',
            'exam_name_ids.*' => 'exists:exam_names,id',
            'due_date' => 'nullable|date',
        ]);

        if ($response = $this->ensureTeacherBelongsToTenant((int) $data['teacher_id'])) {
            return $response;
        }

        $exists = ExamPortion::where('subscription_id', $portion->subscription_id)
            ->where('grade_id', $data['grade_id'])
            ->where('subject_id', $data['subject_id'])
            ->where('exam_name_id', $data['exam_name_ids'][0])
            ->where('id', '!=', $portion->id)
            ->exists();

        if ($exists) {
            return response()->json([
                'message' => 'This Grade, Subject and Exam combination already exists.',
            ], 422);
        }

        $portion->update([
            'teacher_id' => $data['teacher_id'],
            'grade_id' => $data['grade_id'],
            'subject_id' => $data['subject_id'],
            'exam_name_id' => $data['exam_name_ids'][0],
            'due_date' => $data['due_date'] ?? null,
        ]);

        return response()->json([
            'message' => 'Exam portion updated successfully',
            'data' => $portion->load(['teacher', 'grade', 'subject', 'examName']),
        ]);
    }

    public function destroy($id)
    {
        $portion = ExamPortion::findOrFail($id);

        if ($response = $this->ensurePortionAccess($portion)) {
            return $response;
        }

        $portion->delete();

        return response()->json([
            'message' => 'Exam portion deleted successfully',
        ]);
    }

    public function myPortions()
    {
        $teacher = auth()->user();

        if (! $teacher) {
            return response()->json([
                'message' => 'Teacher profile not found.',
            ], 404);
        }

        return ExamPortion::with([
            'examName',
            'grade',
            'subject',
            'lessons.lesson',
            'assignedBy',
            'approvedBy',
        ])
            ->where('subscription_id', $teacher->subscription_id)
            ->where('teacher_id', $teacher->id)
            ->latest()
            ->get();
    }

    public function submit(Request $request, ExamPortion $examPortion)
    {
        $teacher = auth()->user();

        if ($response = $this->ensurePortionAccess($examPortion)) {
            return $response;
        }

        if (
            $teacher?->role === 'teacher' &&
            (! $teacher || (int) $examPortion->teacher_id !== (int) $teacher->id)
        ) {
            return response()->json([
                'message' => 'Unauthorized.',
            ], 403);
        }

        $data = $request->validate([
            'lessons' => 'required|array|min:1',
            'lessons.*.lesson_id' => 'nullable|exists:lessons,id',
            'lessons.*.lesson_title' => 'nullable|string|max:255',
            'lessons.*.topics' => 'nullable|string',
            'lessons.*.learning_objectives' => 'nullable|string',
            'lessons.*.remarks' => 'nullable|string',
        ]);

        $examPortion->lessons()->delete();

        foreach ($data['lessons'] as $lesson) {
            if (empty($lesson['lesson_id']) && empty($lesson['lesson_title'])) {
                return response()->json([
                    'message' => 'Please select a lesson or enter a new lesson name.',
                ], 422);
            }

            $lessonId = $lesson['lesson_id'] ?? null;

            if (! $lessonId && ! empty($lesson['lesson_title'])) {
                $newLesson = Lesson::firstOrCreate(
                    [
                        'subscription_id' => $examPortion->subscription_id,
                        'subject_id' => $examPortion->subject_id,
                        'name' => $lesson['lesson_title'],
                    ],
                    [
                        'grade_id' => $examPortion->grade_id,
                        'stream_id' => $examPortion->stream_id ?? null,
                        'genre' => null,
                        'is_active' => true,
                    ]
                );

                $lessonId = $newLesson->id;
            }

            $examPortion->lessons()->create([
                'lesson_id' => $lessonId,
                'topics' => $lesson['topics'] ?? null,
                'learning_objectives' => $lesson['learning_objectives'] ?? null,
                'remarks' => $lesson['remarks'] ?? null,
            ]);
        }

        $examPortion->update([
            'status' => 'submitted',
            'submitted_at' => now(),
            'approved_by' => null,
            'approved_at' => null,
            'rejection_reason' => null,
        ]);

        $examPortion->load(['examName', 'grade', 'subject', 'teacher']);

        if ($examPortion->assigned_by) {
            notifyUser(
                $examPortion->assigned_by,
                'Exam Syllabus Submitted',
                $examPortion->teacher?->name . ' has submitted syllabus for ' .
                    $examPortion->examName->name . ' - ' .
                    $examPortion->grade->name . ' - ' .
                    $examPortion->subject->name,
                'exam_portion_submitted',
                '/exam-portions'
            );
        }

        return response()->json([
            'message' => 'Exam syllabus submitted successfully',
            'data' => $examPortion->load(['grade', 'subject', 'lessons.lesson']),
        ]);
    }

    public function approve(ExamPortion $examPortion)
    {
        if ($response = $this->ensurePortionAccess($examPortion)) {
            return $response;
        }

        $examPortion->update([
            'status' => 'approved',
            'approved_by' => auth()->id(),
            'approved_at' => now(),
            'rejection_reason' => null,
        ]);

        notifyUser(
            $examPortion->teacher_id,
            'Syllabus Approved',
            'Your exam-wise syllabus has been approved.',
            'exam_portion_approved',
            '/my-exam-portions'
        );

        return response()->json([
            'message' => 'Syllabus approved successfully',
            'data' => $examPortion->load(['teacher', 'grade', 'subject', 'lessons.lesson']),
        ]);
    }

    public function reject(Request $request, ExamPortion $examPortion)
    {
        if ($response = $this->ensurePortionAccess($examPortion)) {
            return $response;
        }

        $data = $request->validate([
            'rejection_reason' => 'required|string|max:1000',
        ]);

        $examPortion->update([
            'status' => 'rejected',
            'approved_by' => auth()->id(),
            'approved_at' => now(),
            'rejection_reason' => $data['rejection_reason'],
        ]);

        notifyUser(
            $examPortion->teacher_id,
            'Syllabus Rejected',
            $data['rejection_reason'],
            'exam_portion_rejected',
            '/my-exam-portions'
        );

        return response()->json([
            'message' => 'Syllabus rejected successfully',
            'data' => $examPortion->load(['teacher', 'grade', 'subject', 'lessons.lesson']),
        ]);
    }
}
