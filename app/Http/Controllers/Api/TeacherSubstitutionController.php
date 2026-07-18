<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TeacherSubstitution;
use App\Services\AcademicPlanning\TeacherSubstitution\SubstituteSuggestionService;
use App\Services\AcademicPlanning\TeacherSubstitution\TeacherSubstitutionService;
use Illuminate\Http\Request;

class TeacherSubstitutionController extends Controller
{
    public function __construct(
        protected TeacherSubstitutionService $service,
        protected SubstituteSuggestionService $suggestions
    ) {}

    private function subscriptionId(): ?int
    {
        return auth()->user()?->subscription_id
            ?? auth()->user()?->subscription?->id;
    }

    public function dashboard(Request $request)
    {
        $date = $request->input('date', now()->toDateString());

        return response()->json(
            $this->service->dashboard(
                $this->subscriptionId(),
                $date,
                $request->integer('academic_year_id') ?: null
            )
        );
    }

    public function pending(Request $request)
    {
        return response()->json(
            $this->service->pending(
                $this->subscriptionId(),
                $request->date,
                $request->integer('academic_year_id') ?: null
            )
        );
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'academic_year_id' => ['nullable', 'exists:academic_years,id'],
            'teacher_availability_exception_id' => ['nullable', 'exists:teacher_availability_exceptions,id'],
            'timetable_entry_id' => ['required', 'exists:timetable_entries,id'],

            'original_teacher_id' => ['nullable', 'exists:users,id'],
            'absent_teacher_id' => ['nullable', 'exists:users,id'],
            'teacher_id' => ['nullable', 'exists:users,id'],

            'substitute_teacher_id' => ['required', 'exists:users,id'],

            'grade_id' => ['nullable', 'exists:grades,id'],
            'section_id' => ['nullable', 'exists:sections,id'],
            'subject_id' => ['nullable', 'exists:subjects,id'],

            'substitution_date' => ['required', 'date'],

            'reason' => ['nullable', 'string', 'max:255'],
            'remarks' => ['nullable', 'string'],

            'status' => ['nullable', 'in:pending,approved,rejected,completed'],
            'ai_score' => ['nullable', 'numeric'],
            'ai_reason' => ['nullable', 'string'],
            'is_ai_suggested' => ['nullable', 'boolean'],
            'ai_suggestions' => ['nullable'],
        ]);

        $originalTeacherId = $data['original_teacher_id']
            ?? $data['absent_teacher_id']
            ?? $data['teacher_id']
            ?? null;

        abort_if(
            ! $originalTeacherId,
            422,
            'The original teacher field is required.'
        );

        $data['subscription_id'] = $this->subscriptionId();
        $data['original_teacher_id'] = $originalTeacherId;
        $data['created_by'] = auth()->id();

        return response()->json([
            'success' => true,
            'message' => 'Substitution created successfully.',
            'data' => $this->service->create($data),
        ]);
    }

    public function suggestions(Request $request)
    {
        $data = $request->validate([
            'academic_year_id' => ['required', 'exists:academic_years,id'],

            'original_teacher_id' => ['nullable', 'exists:users,id'],
            'absent_teacher_id' => ['nullable', 'exists:users,id'],
            'teacher_id' => ['nullable', 'exists:users,id'],

            'school_bell_id' => ['required', 'exists:school_bells,id'],
            'date' => ['required', 'date'],
            'subject_id' => ['nullable', 'exists:subjects,id'],
        ]);

        $teacherId = $data['original_teacher_id']
            ?? $data['absent_teacher_id']
            ?? $data['teacher_id']
            ?? null;

        abort_if(
            ! $teacherId,
            422,
            'The original teacher field is required.'
        );

        return response()->json(
            $this->suggestions->suggest(
                $this->subscriptionId(),
                (int) $data['academic_year_id'],
                (int) $teacherId,
                $data['date'],
                (int) $data['school_bell_id'],
                $data['subject_id'] ?? null
            )
        );
    }

    public function assign(
        Request $request,
        TeacherSubstitution $teacherSubstitution
    ) {
        $this->ensureAccess($teacherSubstitution);

        $request->validate([
            'substitute_teacher_id' => ['required', 'exists:users,id'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Substitute assigned successfully.',
            'data' => $this->service->assign(
                $teacherSubstitution,
                (int) $request->substitute_teacher_id,
                auth()->id()
            ),
        ]);
    }

    public function approve(TeacherSubstitution $teacherSubstitution)
    {
        $this->ensureAccess($teacherSubstitution);

        return response()->json([
            'success' => true,
            'message' => 'Substitution completed successfully.',
            'data' => $this->service->approve(
                $teacherSubstitution,
                auth()->id()
            ),
        ]);
    }

    public function cancel(
        Request $request,
        TeacherSubstitution $teacherSubstitution
    ) {
        $this->ensureAccess($teacherSubstitution);

        $request->validate([
            'remarks' => ['nullable', 'string'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Substitution rejected successfully.',
            'data' => $this->service->cancel(
                $teacherSubstitution,
                $request->remarks
            ),
        ]);
    }

    private function ensureAccess(TeacherSubstitution $substitution): void
    {
        $subscriptionId = $this->subscriptionId();

        if (! $subscriptionId) {
            return;
        }

        abort_if(
            (int) $substitution->subscription_id !== (int) $subscriptionId,
            403,
            'You are not allowed to access this substitution.'
        );
    }
}
