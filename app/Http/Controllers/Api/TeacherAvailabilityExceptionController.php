<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SchoolBell;
use App\Models\TeacherAvailabilityException;
use App\Models\User;
use App\Services\AcademicPlanning\MoveTeacherAvailabilityExceptionService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TeacherAvailabilityExceptionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $subscriptionId = $this->subscriptionId($request);

        $validated = $request->validate([
            'academic_year_id' => ['nullable', ...$this->academicYearRules($subscriptionId)],
            'teacher_id' => ['nullable', ...$this->teacherRules($subscriptionId)],
            'status' => ['nullable', Rule::in(TeacherAvailabilityException::statuses())],
            'from_date' => ['nullable', 'date'],
            'to_date' => ['nullable', 'date', 'after_or_equal:from_date'],
            'per_page' => ['nullable', 'integer', 'between:1,100'],
        ]);

        $query = TeacherAvailabilityException::query()
            ->with(['teacher', 'bell', 'academicYear'])
            ->where('subscription_id', $subscriptionId)
            ->when(
                isset($validated['academic_year_id']),
                fn ($query) => $query->where('academic_year_id', (int) $validated['academic_year_id'])
            )
            ->when(
                isset($validated['teacher_id']),
                fn ($query) => $query->where('teacher_id', (int) $validated['teacher_id'])
            )
            ->when(
                isset($validated['status']),
                fn ($query) => $query->where('status', $validated['status'])
            )
            ->when(
                isset($validated['from_date']),
                fn ($query) => $query->whereDate('exception_date', '>=', $validated['from_date'])
            )
            ->when(
                isset($validated['to_date']),
                fn ($query) => $query->whereDate('exception_date', '<=', $validated['to_date'])
            )
            ->ordered();

        return response()->json([
            'success' => true,
            'data' => $query->paginate((int) ($validated['per_page'] ?? 20)),
        ]);
    }

    public function dashboard(Request $request): JsonResponse
    {
        $subscriptionId = $this->subscriptionId($request);

        $validated = $request->validate([
            'academic_year_id' => ['nullable', ...$this->academicYearRules($subscriptionId)],
            'date' => ['nullable', 'date'],
        ]);

        $today = $validated['date'] ?? now()->toDateString();
        $weekStart = Carbon::parse($today)->startOfWeek()->toDateString();
        $weekEnd = Carbon::parse($today)->endOfWeek()->toDateString();

        $teachers = User::teachers()
            ->where('subscription_id', $subscriptionId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        $bells = SchoolBell::query()
            ->active()
            ->teachingPeriods()
            ->ordered()
            ->get([
                'id',
                'title',
                'period_number',
                'start_time',
                'end_time',
                'sort_order',
            ]);

        $exceptions = TeacherAvailabilityException::query()
            ->with(['teacher', 'bell', 'academicYear'])
            ->where('subscription_id', $subscriptionId)
            ->when(
                isset($validated['academic_year_id']),
                fn ($query) => $query->where('academic_year_id', (int) $validated['academic_year_id'])
            )
            ->active()
            ->whereBetween('exception_date', [$weekStart, $weekEnd])
            ->ordered()
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'teachers' => $teachers,
                'bells' => $bells,
                'exceptions' => $exceptions,
                'stats' => [
                    'total_teachers' => $teachers->count(),
                    'today_exceptions' => $exceptions
                        ->filter(fn ($exception) => $exception->exception_date?->toDateString() === $today)
                        ->count(),
                    'teachers_on_leave' => $exceptions
                        ->filter(fn ($exception) => $exception->exception_date?->toDateString() === $today)
                        ->where('status', TeacherAvailabilityException::STATUS_LEAVE)
                        ->pluck('teacher_id')
                        ->unique()
                        ->count(),
                    'busy_periods' => $exceptions
                        ->filter(fn ($exception) => $exception->exception_date?->toDateString() === $today)
                        ->whereIn('status', TeacherAvailabilityException::blockingStatuses())
                        ->count(),
                ],
                'week' => [
                    'start' => $weekStart,
                    'end' => $weekEnd,
                ],
                'academic_year_id' => $validated['academic_year_id'] ?? null,
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $subscriptionId = $this->subscriptionId($request);
        $data = $this->validatedData($request, $subscriptionId);

        $data['subscription_id'] = $subscriptionId;
        $data['created_by'] = (int) $request->user()->id;
        $data['weekday'] = Carbon::parse($data['exception_date'])->isoWeekday();

        if ((bool) ($data['is_full_day'] ?? false)) {
            $data['school_bell_id'] = null;
        }

        $exception = TeacherAvailabilityException::create($data);

        return response()->json([
            'success' => true,
            'message' => 'Teacher availability exception created successfully.',
            'data' => $exception->fresh(['teacher', 'bell', 'academicYear']),
        ], 201);
    }

    public function update(
        Request $request,
        TeacherAvailabilityException $teacherAvailabilityException
    ): JsonResponse {
        $this->ensureAccess($request, $teacherAvailabilityException);

        $subscriptionId = $this->subscriptionId($request);
        $data = $this->validatedData($request, $subscriptionId);
        $data['weekday'] = Carbon::parse($data['exception_date'])->isoWeekday();

        if ((bool) ($data['is_full_day'] ?? false)) {
            $data['school_bell_id'] = null;
        }

        $teacherAvailabilityException->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Teacher availability exception updated successfully.',
            'data' => $teacherAvailabilityException->fresh(['teacher', 'bell', 'academicYear']),
        ]);
    }

    public function destroy(
        Request $request,
        TeacherAvailabilityException $teacherAvailabilityException
    ): JsonResponse {
        $this->ensureAccess($request, $teacherAvailabilityException);

        $teacherAvailabilityException->delete();

        return response()->json([
            'success' => true,
            'message' => 'Teacher availability exception deleted successfully.',
        ]);
    }

    public function move(
        Request $request,
        TeacherAvailabilityException $teacherAvailabilityException,
        MoveTeacherAvailabilityExceptionService $service
    ): JsonResponse {
        $this->ensureAccess($request, $teacherAvailabilityException);

        $data = $request->validate([
            'exception_date' => ['required', 'date'],
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
        ]);

        $data['weekday'] = Carbon::parse($data['exception_date'])->isoWeekday();

        $exception = $service->move(
            $teacherAvailabilityException,
            $data
        );

        return response()->json([
            'success' => true,
            'message' => 'Teacher availability exception moved successfully.',
            'data' => $exception->fresh(['teacher', 'bell', 'academicYear']),
        ]);
    }

    private function validatedData(Request $request, int $subscriptionId): array
    {
        return $request->validate([
            'academic_year_id' => $this->academicYearRules($subscriptionId),
            'teacher_id' => $this->teacherRules($subscriptionId),
            'exception_date' => ['required', 'date'],
            'weekday' => ['nullable', 'integer', 'between:1,7'],
            'is_full_day' => ['sometimes', 'boolean'],
            'school_bell_id' => [
                'nullable',
                'required_unless:is_full_day,true',
                'integer',
                Rule::exists('school_bells', 'id')->where(
                    fn ($query) => $query
                        ->where('is_active', true)
                        ->where('is_teaching_period', true)
                        ->where('is_break', false)
                        ->where('is_dispersal', false)
                ),
            ],
            'status' => ['required', Rule::in(TeacherAvailabilityException::statuses())],
            'reason' => ['nullable', 'string', 'max:255'],
            'remarks' => ['nullable', 'string'],
            'is_recurring' => ['sometimes', 'boolean'],
            'recurrence_type' => ['nullable', Rule::in(['weekly', 'monthly'])],
            'valid_from' => ['nullable', 'date'],
            'valid_to' => ['nullable', 'date', 'after_or_equal:valid_from'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
    }

    private function academicYearRules(int $subscriptionId): array
    {
        return [
            'required',
            'integer',
            Rule::exists('academic_years', 'id')->where(
                fn ($query) => $query->where('subscription_id', $subscriptionId)
            ),
        ];
    }

    private function teacherRules(int $subscriptionId): array
    {
        return [
            'required',
            'integer',
            Rule::exists('users', 'id')->where(
                fn ($query) => $query
                    ->where('subscription_id', $subscriptionId)
                    ->where('is_active', true)
            ),
        ];
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

    private function ensureAccess(
        Request $request,
        TeacherAvailabilityException $exception
    ): void {
        abort_if(
            (int) $exception->subscription_id !== $this->subscriptionId($request),
            403,
            'You are not allowed to access this exception.'
        );
    }
}
