<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\TimetableRule;
use App\Services\AcademicPlanning\TimetableRuleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TimetableRuleController extends Controller
{
    public function __construct(
        protected TimetableRuleService $service
    ) {}

    public function index(Request $request): JsonResponse
    {
        $subscriptionId = $this->subscriptionId($request);
        $data = $request->validate([
            'academic_year_id' => ['nullable', 'integer', $this->ownedExists('academic_years', $subscriptionId)],
            'constraint_type' => ['nullable', Rule::in(TimetableRule::CONSTRAINT_TYPES)],
            'value_type' => ['nullable', Rule::in(TimetableRule::VALUE_TYPES)],
            'is_active' => ['nullable', 'boolean'],
            'effective_on' => ['nullable', 'date'],
            'search' => ['nullable', 'string', 'max:150'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = TimetableRule::query()
            ->where('subscription_id', $subscriptionId)
            ->with('academicYear')
            ->when(
                array_key_exists('academic_year_id', $data),
                fn ($query) => $query->where('academic_year_id', $data['academic_year_id'])
            )
            ->when($data['constraint_type'] ?? null, fn ($query, $type) => $query->where('constraint_type', $type))
            ->when($data['value_type'] ?? null, fn ($query, $type) => $query->where('value_type', $type))
            ->when(array_key_exists('is_active', $data), fn ($query) => $query->where('is_active', $data['is_active']))
            ->when($data['effective_on'] ?? null, fn ($query, $date) => $query->effectiveOn($date))
            ->when($data['search'] ?? null, function ($query, $search) {
                $query->where(function ($scope) use ($search) {
                    $scope->where('rule_key', 'like', '%' . $search . '%')
                        ->orWhere('description', 'like', '%' . $search . '%');
                });
            })
            ->ordered();

        return response()->json([
            'success' => true,
            'data' => $query->paginate($data['per_page'] ?? 20),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $subscriptionId = $this->subscriptionId($request);
        $rule = $this->service->create(
            $subscriptionId,
            $this->validatedData($request, $subscriptionId)
        );

        return response()->json([
            'success' => true,
            'message' => 'Timetable rule created successfully.',
            'data' => $rule->load('academicYear'),
        ], 201);
    }

    public function show(Request $request, TimetableRule $timetableRule): JsonResponse
    {
        $this->ensureOwned($request, $timetableRule);

        return response()->json([
            'success' => true,
            'data' => $timetableRule->load('academicYear')->append('typed_value'),
        ]);
    }

    public function update(Request $request, TimetableRule $timetableRule): JsonResponse
    {
        $this->ensureOwned($request, $timetableRule);

        return response()->json([
            'success' => true,
            'message' => 'Timetable rule updated successfully.',
            'data' => $this->service->update(
                $timetableRule,
                $this->validatedData($request, (int) $timetableRule->subscription_id)
            ),
        ]);
    }

    public function destroy(Request $request, TimetableRule $timetableRule): JsonResponse
    {
        $this->ensureOwned($request, $timetableRule);
        $this->service->delete($timetableRule);

        return response()->json([
            'success' => true,
            'message' => 'Timetable rule deleted successfully.',
        ]);
    }

    public function activate(Request $request, TimetableRule $timetableRule): JsonResponse
    {
        $this->ensureOwned($request, $timetableRule);

        return response()->json([
            'success' => true,
            'message' => 'Timetable rule activated successfully.',
            'data' => $this->service->setActive($timetableRule, true),
        ]);
    }

    public function deactivate(Request $request, TimetableRule $timetableRule): JsonResponse
    {
        $this->ensureOwned($request, $timetableRule);

        return response()->json([
            'success' => true,
            'message' => 'Timetable rule deactivated successfully.',
            'data' => $this->service->setActive($timetableRule, false),
        ]);
    }

    public function duplicate(Request $request, TimetableRule $timetableRule): JsonResponse
    {
        $this->ensureOwned($request, $timetableRule);
        $subscriptionId = (int) $timetableRule->subscription_id;
        $overrides = $request->validate([
            'rule_key' => ['required', 'string', 'max:150'],
            'academic_year_id' => ['nullable', 'integer', $this->ownedExists('academic_years', $subscriptionId)],
            'constraint_type' => ['nullable', Rule::in(TimetableRule::CONSTRAINT_TYPES)],
            'priority' => ['nullable', 'integer', 'min:1', 'max:10'],
            'description' => ['nullable', 'string', 'max:1000'],
            'effective_from' => ['nullable', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Timetable rule copied successfully.',
            'data' => $this->service->duplicate($timetableRule, $overrides),
        ], 201);
    }

    private function validatedData(Request $request, int $subscriptionId): array
    {
        $data = $request->validate([
            'academic_year_id' => ['nullable', 'integer', $this->ownedExists('academic_years', $subscriptionId)],
            'rule_key' => ['required', 'string', 'max:150', 'regex:/^[a-z0-9_.-]+$/'],
            'rule_value' => ['required'],
            'value_type' => ['required', Rule::in(TimetableRule::VALUE_TYPES)],
            'constraint_type' => ['required', Rule::in(TimetableRule::CONSTRAINT_TYPES)],
            'priority' => ['nullable', 'integer', 'min:1', 'max:10'],
            'description' => ['nullable', 'string', 'max:1000'],
            'effective_from' => ['nullable', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        if (($data['value_type'] ?? null) === 'json' && ! is_array($data['rule_value'])) {
            validator(
                ['rule_value' => $data['rule_value']],
                ['rule_value' => ['array']]
            )->validate();
        }

        return $data;
    }

    private function ownedExists(string $table, int $subscriptionId): mixed
    {
        return Rule::exists($table, 'id')->where('subscription_id', $subscriptionId);
    }

    private function ensureOwned(Request $request, TimetableRule $rule): void
    {
        abort_unless(
            (int) $rule->subscription_id === $this->subscriptionId($request),
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
