<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TeacherAvailabilityException;
use App\Models\TeacherSubstitution;
use App\Models\TimetableEntry;
use App\Services\AcademicPlanning\TeacherSubstitution\SubstituteSuggestionService;
use App\Services\AcademicPlanning\TeacherSubstitution\TeacherSubstitutionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TeacherSubstitutionController extends Controller
{
    public function __construct(
        protected TeacherSubstitutionService $service,
        protected SubstituteSuggestionService $suggestions
    ) {}

    public function dashboard(Request $request): JsonResponse
    {
        $subscriptionId = $this->subscriptionId($request);

        $validated = $request->validate([
            'date' => ['nullable', 'date'],
            'academic_year_id' => $this->academicYearRules($subscriptionId, false),
        ]);

        return response()->json(
            $this->service->dashboard(
                $subscriptionId,
                $validated['date'] ?? now()->toDateString(),
                isset($validated['academic_year_id'])
                    ? (int) $validated['academic_year_id']
                    : null
            )
        );
    }

    public function pending(Request $request): JsonResponse
    {
        $subscriptionId = $this->subscriptionId($request);

        $validated = $request->validate([
            'date' => ['nullable', 'date'],
            'academic_year_id' => $this->academicYearRules($subscriptionId, false),
        ]);

        return response()->json(
            $this->service->pending(
                $subscriptionId,
                $validated['date'] ?? null,
                isset($validated['academic_year_id'])
                    ? (int) $validated['academic_year_id']
                    : null
            )
        );
    }

    public function store(Request $request): JsonResponse
    {
        $subscriptionId = $this->subscriptionId($request);

        $data = $request->validate([
            'academic_year_id' => $this->academicYearRules($subscriptionId, false),
            'teacher_availability_exception_id' => [
                'nullable',
                'integer',
                Rule::exists('teacher_availability_exceptions', 'id')->where(
                    fn ($query) => $query->where('subscription_id', $subscriptionId)
                ),
            ],
            'timetable_entry_id' => ['required', 'integer'],
            'original_teacher_id' => $this->teacherRules($subscriptionId, false),
            'absent_teacher_id' => $this->teacherRules($subscriptionId, false),
            'teacher_id' => $this->teacherRules($subscriptionId, false),
            'substitute_teacher_id' => [
                ...$this->teacherRules($subscriptionId),
                'different:original_teacher_id',
                'different:absent_teacher_id',
                'different:teacher_id',
            ],
            'grade_id' => ['nullable', 'integer', 'exists:grades,id'],
            'section_id' => ['nullable', 'integer', 'exists:sections,id'],
            'subject_id' => ['nullable', 'integer', 'exists:subjects,id'],
            'substitution_date' => ['required', 'date'],
            'reason' => ['nullable', 'string', 'max:255'],
            'remarks' => ['nullable', 'string'],
            'status' => ['nullable', Rule::in(TeacherSubstitution::statuses())],
            'ai_score' => ['nullable', 'numeric', 'between:0,100'],
            'ai_reason' => ['nullable', 'string'],
            'is_ai_suggested' => ['nullable', 'boolean'],
            'ai_suggestions' => ['nullable', 'array'],
        ]);

        $originalTeacherId = $data['original_teacher_id']
            ?? $data['absent_teacher_id']
            ?? $data['teacher_id']
            ?? null;

        abort_if(
            !$originalTeacherId,
            422,
            'The original teacher field is required.'
        );

        $entry = TimetableEntry::query()
            ->forSubscription($subscriptionId)
            ->findOrFail((int) $data['timetable_entry_id']);

        abort_if(
            (int) $entry->teacher_id !== (int) $originalTeacherId,
            422,
            'The original teacher does not match the timetable entry.'
        );

        if (!empty($data['teacher_availability_exception_id'])) {
            $exception = TeacherAvailabilityException::query()
                ->where('subscription_id', $subscriptionId)
                ->findOrFail((int) $data['teacher_availability_exception_id']);

            abort_if(
                (int) $exception->teacher_id !== (int) $originalTeacherId,
                422,
                'The availability exception does not belong to the original teacher.'
            );
        }

        $data['subscription_id'] = $subscriptionId;
        $data['original_teacher_id'] = (int) $originalTeacherId;
        $data['created_by'] = (int) $request->user()->id;

        unset($data['absent_teacher_id'], $data['teacher_id']);

        return response()->json([
            'success' => true,
            'message' => 'Substitution created successfully.',
            'data' => $this->service->create($data),
        ], 201);
    }

    public function suggestions(Request $request): JsonResponse
    {
        $subscriptionId = $this->subscriptionId($request);

        $data = $request->validate([
            'academic_year_id' => $this->academicYearRules($subscriptionId),
            'original_teacher_id' => $this->teacherRules($subscriptionId, false),
            'absent_teacher_id' => $this->teacherRules($subscriptionId, false),
            'teacher_id' => $this->teacherRules($subscriptionId, false),
            'school_bell_id' => [
                'required',
                'integer',
                Rule::exists('school_bells', 'id')->where(
                    fn ($query) => $query
                        ->where('is_active', true)
                        ->where('is_teaching_period', true)
                        ->where('is_break', false)
                        ->where('is_dispersal', false)
                ),
            ],
            'date' => ['required', 'date'],
            'subject_id' => ['nullable', 'integer', 'exists:subjects,id'],
        ]);

        $teacherId = $data['original_teacher_id']
            ?? $data['absent_teacher_id']
            ?? $data['teacher_id']
            ?? null;

        abort_if(
            !$teacherId,
            422,
            'The original teacher field is required.'
        );

        return response()->json(
            $this->suggestions->suggest(
                $subscriptionId,
                (int) $data['academic_year_id'],
                (int) $teacherId,
                $data['date'],
                (int) $data['school_bell_id'],
                isset($data['subject_id']) ? (int) $data['subject_id'] : null
            )
        );
    }

    public function assign(
        Request $request,
        TeacherSubstitution $teacherSubstitution
    ): JsonResponse {
        $subscriptionId = $this->subscriptionId($request);
        $this->ensureAccess($teacherSubstitution, $subscriptionId);

        $validated = $request->validate([
            'substitute_teacher_id' => [
                ...$this->teacherRules($subscriptionId),
                Rule::notIn([(int) $teacherSubstitution->original_teacher_id]),
            ],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Substitute assigned successfully.',
            'data' => $this->service->assign(
                $teacherSubstitution,
                (int) $validated['substitute_teacher_id'],
                (int) $request->user()->id
            ),
        ]);
    }

    public function approve(
        Request $request,
        TeacherSubstitution $teacherSubstitution
    ): JsonResponse {
        $subscriptionId = $this->subscriptionId($request);
        $this->ensureAccess($teacherSubstitution, $subscriptionId);

        return response()->json([
            'success' => true,
            'message' => 'Substitution completed successfully.',
            'data' => $this->service->approve(
                $teacherSubstitution,
                (int) $request->user()->id
            ),
        ]);
    }

    public function cancel(
        Request $request,
        TeacherSubstitution $teacherSubstitution
    ): JsonResponse {
        $subscriptionId = $this->subscriptionId($request);
        $this->ensureAccess($teacherSubstitution, $subscriptionId);

        $validated = $request->validate([
            'remarks' => ['nullable', 'string'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Substitution rejected successfully.',
            'data' => $this->service->cancel(
                $teacherSubstitution,
                $validated['remarks'] ?? null
            ),
        ]);
    }

    private function subscriptionId(Request $request): int
    {
        $subscriptionId = $request->user()?->subscription_id
            ?? $request->user()?->subscription?->id;

        abort_if(
            !$subscriptionId,
            403,
            'No subscription assigned to your account.'
        );

        return (int) $subscriptionId;
    }

    private function teacherRules(
        int $subscriptionId,
        bool $required = true
    ): array {
        return [
            $required ? 'required' : 'nullable',
            'integer',
            Rule::exists('users', 'id')->where(
                fn ($query) => $query
                    ->where('subscription_id', $subscriptionId)
                    ->where('is_active', true)
            ),
        ];
    }

    private function academicYearRules(
        int $subscriptionId,
        bool $required = true
    ): array {
        return [
            $required ? 'required' : 'nullable',
            'integer',
            Rule::exists('academic_years', 'id')->where(
                fn ($query) => $query
                    ->where('subscription_id', $subscriptionId)
                    ->where('is_active', true)
            ),
        ];
    }

    private function ensureAccess(
        TeacherSubstitution $substitution,
        int $subscriptionId
    ): void {
        abort_if(
            (int) $substitution->subscription_id !== $subscriptionId,
            403,
            'You are not allowed to access this substitution.'
        );
    }
}
