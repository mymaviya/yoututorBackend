<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ParallelGroup;
use App\Services\AcademicPlanning\ParallelGroupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ParallelGroupController extends Controller
{
    public function __construct(
        protected ParallelGroupService $service
    ) {}

    public function index(Request $request): JsonResponse
    {
        $subscriptionId = $this->subscriptionId($request);
        $data = $request->validate([
            'grade_id' => ['nullable', 'integer', Rule::exists('grades', 'id')],
            'is_active' => ['nullable', 'boolean'],
            'same_period_required' => ['nullable', 'boolean'],
            'search' => ['nullable', 'string', 'max:150'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = ParallelGroup::query()
            ->where('subscription_id', $subscriptionId)
            ->with(['grade', 'items.subject', 'items.teacher'])
            ->withCount(['items', 'activeTimetableEntries'])
            ->when($data['grade_id'] ?? null, fn ($query, $gradeId) => $query->where('grade_id', $gradeId))
            ->when(array_key_exists('is_active', $data), fn ($query) => $query->where('is_active', $data['is_active']))
            ->when(
                array_key_exists('same_period_required', $data),
                fn ($query) => $query->where('same_period_required', $data['same_period_required'])
            )
            ->when($data['search'] ?? null, fn ($query, $search) => $query->where('name', 'like', '%' . $search . '%'))
            ->orderByDesc('is_active')
            ->orderBy('grade_id')
            ->orderBy('name');

        return response()->json([
            'success' => true,
            'data' => $query->paginate($data['per_page'] ?? 20),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $subscriptionId = $this->subscriptionId($request);
        $group = $this->service->create(
            $subscriptionId,
            $this->validatedData($request, $subscriptionId)
        );

        return response()->json([
            'success' => true,
            'message' => 'Parallel group created successfully.',
            'data' => $group,
        ], 201);
    }

    public function show(Request $request, ParallelGroup $parallelGroup): JsonResponse
    {
        $this->ensureOwned($request, $parallelGroup);

        return response()->json([
            'success' => true,
            'data' => $parallelGroup->load(['grade', 'items.subject', 'items.teacher'])
                ->loadCount(['items', 'activeTimetableEntries']),
        ]);
    }

    public function update(Request $request, ParallelGroup $parallelGroup): JsonResponse
    {
        $this->ensureOwned($request, $parallelGroup);
        $group = $this->service->update(
            $parallelGroup,
            $this->validatedData($request, (int) $parallelGroup->subscription_id, $parallelGroup)
        );

        return response()->json([
            'success' => true,
            'message' => 'Parallel group updated successfully.',
            'data' => $group,
        ]);
    }

    public function destroy(Request $request, ParallelGroup $parallelGroup): JsonResponse
    {
        $this->ensureOwned($request, $parallelGroup);
        $this->service->delete($parallelGroup);

        return response()->json([
            'success' => true,
            'message' => 'Parallel group deleted successfully.',
        ]);
    }

    public function activate(Request $request, ParallelGroup $parallelGroup): JsonResponse
    {
        $this->ensureOwned($request, $parallelGroup);

        return response()->json([
            'success' => true,
            'message' => 'Parallel group activated successfully.',
            'data' => $this->service->setActive($parallelGroup, true),
        ]);
    }

    public function deactivate(Request $request, ParallelGroup $parallelGroup): JsonResponse
    {
        $this->ensureOwned($request, $parallelGroup);

        return response()->json([
            'success' => true,
            'message' => 'Parallel group deactivated successfully.',
            'data' => $this->service->setActive($parallelGroup, false),
        ]);
    }

    private function validatedData(
        Request $request,
        int $subscriptionId,
        ?ParallelGroup $group = null
    ): array {
        $data = $request->validate([
            'grade_id' => ['required', 'integer', Rule::exists('grades', 'id')],
            'name' => [
                'required',
                'string',
                'max:150',
                Rule::unique('parallel_groups', 'name')
                    ->where(fn ($query) => $query
                        ->where('subscription_id', $subscriptionId)
                        ->where('grade_id', $request->input('grade_id')))
                    ->ignore($group?->getKey()),
            ],
            'same_period_required' => ['sometimes', 'boolean'],
            'period_number_fixed' => ['sometimes', 'boolean'],
            'preferred_period_number' => ['nullable', 'integer', 'min:1', 'max:50'],
            'weekly_periods' => ['required', 'integer', 'min:1', 'max:50'],
            'prefer_morning' => ['sometimes', 'boolean'],
            'prefer_last_period' => ['sometimes', 'boolean'],
            'prefer_saturday' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'items' => ['required', 'array', 'min:2'],
            'items.*.subject_id' => [
                'required',
                'integer',
                Rule::exists('subjects', 'id')->where('subscription_id', $subscriptionId),
            ],
            'items.*.teacher_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where('subscription_id', $subscriptionId),
            ],
            'items.*.stream_ids' => ['nullable', 'array'],
            'items.*.stream_ids.*' => [
                'integer',
                Rule::exists('streams', 'id')->where('subscription_id', $subscriptionId),
            ],
            'items.*.student_group_name' => ['nullable', 'string', 'max:150'],
            'items.*.teacher_split_order' => ['nullable', 'integer', 'min:1', 'max:100'],
            'items.*.room_no' => ['nullable', 'string', 'max:100'],
            'items.*.is_active' => ['sometimes', 'boolean'],
        ]);

        if (($data['period_number_fixed'] ?? false) && empty($data['preferred_period_number'])) {
            throw ValidationException::withMessages([
                'preferred_period_number' => 'A preferred period number is required when the period is fixed.',
            ]);
        }

        $subjectGradeIds = DB::table('subjects')
            ->whereIn('id', collect($data['items'])->pluck('subject_id'))
            ->pluck('grade_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique();

        if ($subjectGradeIds->contains(fn (int $gradeId) => $gradeId !== (int) $data['grade_id'])) {
            throw ValidationException::withMessages([
                'items' => 'Every subject in the parallel group must belong to the selected grade.',
            ]);
        }

        return $data;
    }

    private function ensureOwned(Request $request, ParallelGroup $group): void
    {
        abort_unless(
            (int) $group->subscription_id === $this->subscriptionId($request),
            404
        );
    }

    private function subscriptionId(Request $request): int
    {
        $subscriptionId = $request->user()?->subscription_id
            ?? $request->user()?->subscription?->id;

        abort_if(
            ! is_numeric($subscriptionId) || (int) $subscriptionId <= 0,
            403,
            'A valid subscription is required.'
        );

        return (int) $subscriptionId;
    }
}
