<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SchoolBell;
use App\Models\TeacherAvailabilityException;
use App\Models\TeacherSubstitution;
use App\Models\User;
use App\Services\AcademicPlanning\MoveTeacherAvailabilityExceptionService;
use App\Services\AcademicPlanning\AutoSubstitutionGeneratorService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class TeacherAvailabilityExceptionController extends Controller
{
    public function index(Request $request)
    {
        $subscriptionId = $this->subscriptionId();

        $query = TeacherAvailabilityException::with(['teacher', 'bell', 'academicYear'])
            ->where('subscription_id', $subscriptionId);

        if ($request->filled('teacher_id')) {
            $query->where('teacher_id', $request->teacher_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('from_date') && $request->filled('to_date')) {
            $query->whereBetween('exception_date', [
                $request->from_date,
                $request->to_date,
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => $query->latest()->paginate($request->get('per_page', 20)),
        ]);
    }

    public function dashboard(Request $request)
    {
        $subscriptionId = $this->subscriptionId();

        $today = $request->get('date', now()->toDateString());
        $weekStart = Carbon::parse($today)->startOfWeek()->toDateString();
        $weekEnd = Carbon::parse($today)->endOfWeek()->toDateString();

        $teachers = User::teachers()
            ->where('subscription_id', $subscriptionId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        $bells = SchoolBell::where('is_active', true)
            ->where('is_teaching_period', true)
            ->orderBy('sort_order')
            ->get(['id', 'title', 'period_number', 'start_time', 'end_time']);

        $exceptions = TeacherAvailabilityException::with(['teacher', 'bell'])
            ->where('subscription_id', $subscriptionId)
            ->where('is_active', true)
            ->whereBetween('exception_date', [$weekStart, $weekEnd])
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'teachers' => $teachers,
                'bells' => $bells,
                'exceptions' => $exceptions,
                'stats' => [
                    'total_teachers' => $teachers->count(),
                    'today_exceptions' => $exceptions->where('exception_date', $today)->count(),
                    'teachers_on_leave' => $exceptions
                        ->where('exception_date', $today)
                        ->where('status', 'leave')
                        ->pluck('teacher_id')
                        ->unique()
                        ->count(),
                    'busy_periods' => $exceptions
                        ->where('exception_date', $today)
                        ->whereIn('status', ['busy', 'meeting', 'training', 'exam_duty', 'assembly', 'blocked'])
                        ->count(),
                ],
                'week' => [
                    'start' => $weekStart,
                    'end' => $weekEnd,
                ],
            ],
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validatedData($request);

        $data['subscription_id'] = $this->subscriptionId();
        $data['created_by'] = auth()->id();

        if (($data['is_full_day'] ?? false) === true) {
            $data['school_bell_id'] = null;
        }

        $exception = TeacherAvailabilityException::create($data);

        return response()->json([
            'success' => true,
            'message' => 'Teacher availability exception created successfully.',
            'data' => $exception->fresh(['teacher', 'bell', 'academicYear']),
        ]);
    }

    public function update(Request $request, TeacherAvailabilityException $teacherAvailabilityException)
    {
        $this->ensureAccess($teacherAvailabilityException);

        $data = $this->validatedData($request);

        if (($data['is_full_day'] ?? false) === true) {
            $data['school_bell_id'] = null;
        }

        $teacherAvailabilityException->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Teacher availability exception updated successfully.',
            'data' => $teacherAvailabilityException->fresh(['teacher', 'bell', 'academicYear']),
        ]);
    }

    public function destroy(TeacherAvailabilityException $teacherAvailabilityException)
    {
        $this->ensureAccess($teacherAvailabilityException);

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
    ) {
        $this->ensureAccess($teacherAvailabilityException);

        $data = $request->validate([
            'exception_date' => 'required|date',
            'weekday' => 'required|string|max:20',
            'school_bell_id' => 'required|exists:school_bells,id',
        ]);

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

    private function validatedData(Request $request): array
    {
        return $request->validate([
            'academic_year_id' => 'nullable|exists:academic_years,id',
            'teacher_id' => 'required|exists:users,id',
            'exception_date' => 'required|date',
            'weekday' => 'required|string|max:20',
            'school_bell_id' => 'nullable|exists:school_bells,id',
            'status' => 'required|in:busy,leave,meeting,training,exam_duty,assembly,blocked,extra_class',
            'reason' => 'nullable|string|max:255',
            'remarks' => 'nullable|string',
            'is_full_day' => 'boolean',
            'is_recurring' => 'boolean',
            'recurrence_type' => 'nullable|in:weekly,monthly',
            'valid_from' => 'nullable|date',
            'valid_to' => 'nullable|date|after_or_equal:valid_from',
            'is_active' => 'boolean',
        ]);
    }

    
    private function subscriptionId(): int
    {
        $subscriptionId = auth()->user()?->subscription_id
            ?? auth()->user()?->subscription?->id;

        abort_if(
            ! $subscriptionId,
            403,
            'No subscription assigned to your account.'
        );

        return (int) $subscriptionId;
    }

    private function ensureAccess(TeacherAvailabilityException $exception): void
    {
        abort_if(
            (int) $exception->subscription_id !== $this->subscriptionId(),
            403,
            'You are not allowed to access this exception.'
        );
    }
}
