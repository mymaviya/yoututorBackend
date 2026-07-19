<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\TimetableTemplate;
use App\Services\AcademicPlanning\TimetableTemplateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TimetableTemplateController extends Controller
{
    public function __construct(
        protected TimetableTemplateService $service
    ) {}

    public function index(Request $request): JsonResponse
    {
        $subscriptionId = $this->subscriptionId($request);
        $data = $request->validate([
            'type' => ['nullable', Rule::in(TimetableTemplate::TYPES)],
            'is_active' => ['nullable', 'boolean'],
            'is_default' => ['nullable', 'boolean'],
            'effective_on' => ['nullable', 'date'],
            'search' => ['nullable', 'string', 'max:150'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = TimetableTemplate::query()
            ->where('subscription_id', $subscriptionId)
            ->withCount('weeklyTimetables')
            ->when($data['type'] ?? null, fn ($query, $type) => $query->where('type', $type))
            ->when(array_key_exists('is_active', $data), fn ($query) => $query->where('is_active', $data['is_active']))
            ->when(array_key_exists('is_default', $data), fn ($query) => $query->where('is_default', $data['is_default']))
            ->when($data['effective_on'] ?? null, fn ($query, $date) => $query->effectiveOn($date))
            ->when($data['search'] ?? null, fn ($query, $search) => $query->where('name', 'like', '%' . $search . '%'))
            ->orderByDesc('is_default')
            ->orderByDesc('is_active')
            ->orderByDesc('effective_from')
            ->orderBy('name');

        return response()->json([
            'success' => true,
            'data' => $query->paginate($data['per_page'] ?? 20),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $subscriptionId = $this->subscriptionId($request);
        $template = $this->service->create(
            $subscriptionId,
            $this->validatedData($request, $subscriptionId)
        );

        return response()->json([
            'success' => true,
            'message' => 'Timetable template created successfully.',
            'data' => $template,
        ], 201);
    }

    public function show(Request $request, TimetableTemplate $timetableTemplate): JsonResponse
    {
        $this->ensureOwned($request, $timetableTemplate);

        return response()->json([
            'success' => true,
            'data' => $timetableTemplate->loadCount('weeklyTimetables'),
        ]);
    }

    public function update(Request $request, TimetableTemplate $timetableTemplate): JsonResponse
    {
        $this->ensureOwned($request, $timetableTemplate);
        $template = $this->service->update(
            $timetableTemplate,
            $this->validatedData($request, (int) $timetableTemplate->subscription_id, $timetableTemplate)
        );

        return response()->json([
            'success' => true,
            'message' => 'Timetable template updated successfully.',
            'data' => $template,
        ]);
    }

    public function destroy(Request $request, TimetableTemplate $timetableTemplate): JsonResponse
    {
        $this->ensureOwned($request, $timetableTemplate);
        $this->service->delete($timetableTemplate);

        return response()->json([
            'success' => true,
            'message' => 'Timetable template deleted successfully.',
        ]);
    }

    public function activate(Request $request, TimetableTemplate $timetableTemplate): JsonResponse
    {
        $this->ensureOwned($request, $timetableTemplate);
        $data = $request->validate([
            'make_default' => ['nullable', 'boolean'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Timetable template activated successfully.',
            'data' => $this->service->activate($timetableTemplate, (bool) ($data['make_default'] ?? false)),
        ]);
    }

    public function deactivate(Request $request, TimetableTemplate $timetableTemplate): JsonResponse
    {
        $this->ensureOwned($request, $timetableTemplate);

        return response()->json([
            'success' => true,
            'message' => 'Timetable template deactivated successfully.',
            'data' => $this->service->deactivate($timetableTemplate),
        ]);
    }

    public function duplicate(Request $request, TimetableTemplate $timetableTemplate): JsonResponse
    {
        $this->ensureOwned($request, $timetableTemplate);
        $subscriptionId = (int) $timetableTemplate->subscription_id;
        $overrides = $request->validate([
            'name' => [
                'required',
                'string',
                'max:150',
                Rule::unique('timetable_templates', 'name')->where('subscription_id', $subscriptionId),
            ],
            'type' => ['nullable', Rule::in(TimetableTemplate::TYPES)],
            'effective_from' => ['nullable', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'is_active' => ['nullable', 'boolean'],
            'is_default' => ['nullable', 'boolean'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Timetable template copied successfully.',
            'data' => $this->service->clone($timetableTemplate, $overrides),
        ], 201);
    }

    private function validatedData(
        Request $request,
        int $subscriptionId,
        ?TimetableTemplate $template = null
    ): array {
        return $request->validate([
            'name' => [
                'required',
                'string',
                'max:150',
                Rule::unique('timetable_templates', 'name')
                    ->where('subscription_id', $subscriptionId)
                    ->ignore($template?->getKey()),
            ],
            'type' => ['required', Rule::in(TimetableTemplate::TYPES)],
            'effective_from' => ['nullable', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'is_default' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
    }

    private function ensureOwned(Request $request, TimetableTemplate $template): void
    {
        abort_unless(
            (int) $template->subscription_id === $this->subscriptionId($request),
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
