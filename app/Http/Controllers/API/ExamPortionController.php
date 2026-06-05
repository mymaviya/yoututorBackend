<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\ExamPortion;
use App\Models\Lesson;
use Illuminate\Http\Request;

class ExamPortionController extends Controller
{
    public function index(Request $request)
    {
        $query = ExamPortion::with([
            'teacher.user',
            'grade',
            'subject',
            'examName',
            'lessons.lesson',
            'assignedBy',
            'approvedBy',
        ])->latest();

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
            'teacher_id' => 'required|exists:teachers,id',
            'grade_id' => 'required|exists:grades,id',
            'subject_id' => 'required|exists:subjects,id',
            'exam_name_ids' => 'required|array|min:1',
            'exam_name_ids.*' => 'exists:exam_names,id',
            'due_date' => 'nullable|date',
        ]);

        $created = [];
        $duplicates = [];

        foreach ($data['exam_name_ids'] as $examNameId) {

            $exists = ExamPortion::where('grade_id', $data['grade_id'])
                ->where('subject_id', $data['subject_id'])
                ->where('exam_name_id', $examNameId)
                ->exists();

            if ($exists) {
                $duplicates[] = $examNameId;
            }
        }

        if (!empty($duplicates)) {

            $examNames = \App\Models\ExamName::whereIn('id', $duplicates)
                ->pluck('name')
                ->implode(', ');

            return response()->json([
                'message' => 'Portion already exists for: ' . $examNames,
            ], 500);
        }

        foreach ($data['exam_name_ids'] as $examNameId) {
            $portion = ExamPortion::create([
                'teacher_id' => $data['teacher_id'],
                'grade_id' => $data['grade_id'],
                'subject_id' => $data['subject_id'],
                'exam_name_id' => $examNameId,
                'due_date' => $data['due_date'] ?? null,
                'status' => 'assigned',
                'assigned_by' => auth()->id(),
            ]);

            $portion->load(['teacher.user', 'grade', 'subject', 'examName']);

            notifyUser(
                $portion->teacher->user_id,
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
            'teacher.user',
            'grade',
            'subject',
            'examName',
            'lessons.lesson',
            'assignedBy',
            'approvedBy',
        ])->findOrFail($id);

        return response()->json($portion);
    }

    public function update(Request $request, $id)
    {
        $portion = ExamPortion::findOrFail($id);

        $data = $request->validate([
            'teacher_id' => 'required|exists:teachers,id',
            'grade_id' => 'required|exists:grades,id',
            'subject_id' => 'required|exists:subjects,id',
            'exam_name_ids' => 'required|array|min:1',
            'exam_name_ids.*' => 'exists:exam_names,id',
            'due_date' => 'nullable|date',
        ]);

        $exists = ExamPortion::where('grade_id', $data['grade_id'])
            ->where('subject_id', $data['subject_id'])
            ->where('exam_name_id', $data['exam_name_ids'][0])
            ->where('id', '!=', $portion->id)
            ->exists();

        if ($exists) {
            return response()->json([
                'message' => 'This Grade, Subject and Exam combination already exists.'
            ], 500);
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
            'data' => $portion->load(['teacher.user', 'grade', 'subject', 'examName']),
        ]);
    }

    public function destroy($id)
    {
        ExamPortion::findOrFail($id)->delete();

        return response()->json([
            'message' => 'Exam portion deleted successfully',
        ]);
    }

    public function myPortions()
    {
        $teacher = auth()->user()->teacher;

        if (!$teacher) {
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
            ->where('teacher_id', $teacher->id)
            ->latest()
            ->get();
    }

    public function submit(Request $request, ExamPortion $examPortion)
    {
        $teacher = auth()->user()->teacher;

        if (
            auth()->user()->role === 'teacher' &&
            (!$teacher || $examPortion->teacher_id !== $teacher->id)
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
                    'message' => 'Please select a lesson or enter a new lesson name.'
                ], 422);
            }

            $lessonId = $lesson['lesson_id'] ?? null;

            if (!$lessonId && !empty($lesson['lesson_title'])) {
                $newLesson = Lesson::firstOrCreate(
                    [
                        'subject_id' => $examPortion->subject_id,
                        'title' => $lesson['lesson_title'],
                    ],
                    [
                        'is_active' => 1,
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

        $examPortion->load(['examName', 'grade', 'subject', 'teacher.user']);

        if ($examPortion->assigned_by) {
            notifyUser(
                $examPortion->assigned_by,
                'Exam Syllabus Submitted',
                $examPortion->teacher->user->name . ' has submitted syllabus for ' .
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
        $examPortion->update([
            'status' => 'approved',
            'approved_by' => auth()->id(),
            'approved_at' => now(),
            'rejection_reason' => null,
        ]);

        notifyUser(
            $examPortion->teacher->user_id,
            'Syllabus Approved',
            'Your exam-wise syllabus has been approved.',
            'exam_portion_approved',
            '/my-exam-portions'
        );

        return response()->json([
            'message' => 'Syllabus approved successfully',
            'data' => $examPortion->load(['teacher.user', 'grade', 'subject', 'lessons.lesson']),
        ]);
    }

    public function reject(Request $request, ExamPortion $examPortion)
    {
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
            $examPortion->teacher->user_id,
            'Syllabus Rejected',
            $data['rejection_reason'],
            'exam_portion_rejected',
            '/my-exam-portions'
        );

        return response()->json([
            'message' => 'Syllabus rejected successfully',
            'data' => $examPortion->load(['teacher.user', 'grade', 'subject', 'lessons.lesson']),
        ]);
    }
}
