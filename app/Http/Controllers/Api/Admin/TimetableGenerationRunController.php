<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\TimetableGenerationRun;
use App\Services\AcademicPlanning\TimetableGenerationRunService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TimetableGenerationRunController extends Controller
{
    public function __construct(
        protected TimetableGenerationRunService $service
    ) {}

    public function index(Request $request): JsonResponse
    {
        $subscriptionId = $this->subscriptionId($request);
        $data = $request->validate([
            'mode' => ['nullable', Rule::in(TimetableGenerationRun::MODES)],
            'status' => ['nullable', Rule::in(TimetableGenerationRun::STATUSES)],
            'is_preview' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $runs = TimetableGenerationRun::query()
            ->where('subscription_id', $subscriptionId)
            ->with('user:id,name,email')
            ->withCount(['conflicts', 'retries'])
            ->when($data['mode'] ?? null, fn ($query, $mode) => $query->where('mode', $mode))
            ->when($data['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when(
                array_key_exists('is_preview', $data),
                fn ($query) => $query->where('is_preview', $data['is_preview'])
            )
            ->latestFirst()
            ->paginate($data['per_page'] ?? 20);

        return response()->json([
            'success' => true,
            'data' => $runs,
        ]);
    }

    public function show(
        Request $request,
        TimetableGenerationRun $timetableGenerationRun
    ): JsonResponse {
        $this->ensureOwned($request, $timetableGenerationRun);

        return response()->json([
            'success' => true,
            'data' => $timetableGenerationRun->load([
                'user:id,name,email',
                'parentRun:id,status,mode,is_preview,created_at',
                'retries:id,parent_run_id,status,mode,is_preview,created_at,completed_at',
            ])->loadCount(['conflicts', 'retries']),
        ]);
    }

    public function conflicts(
        Request $request,
        TimetableGenerationRun $timetableGenerationRun
    ): JsonResponse {
        $this->ensureOwned($request, $timetableGenerationRun);
        $data = $request->validate([
            'severity' => ['nullable', Rule::in(['warning', 'error'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        return response()->json([
            'success' => true,
            'data' => $timetableGenerationRun->conflicts()
                ->when(
                    $data['severity'] ?? null,
                    fn ($query, $severity) => $query->where('severity', $severity)
                )
                ->orderBy('item_index')
                ->orderByDesc('severity')
                ->orderBy('id')
                ->paginate($data['per_page'] ?? 50),
        ]);
    }

    public function retry(
        Request $request,
        TimetableGenerationRun $timetableGenerationRun
    ): JsonResponse {
        $this->ensureOwned($request, $timetableGenerationRun);
        abort_if($timetableGenerationRun->status === 'running', 422, 'A running generation cannot be retried.');

        $payload = (array) $timetableGenerationRun->request_payload;
        $result = $timetableGenerationRun->mode === 'batch'
            ? $this->service->executeBatch(
                (int) $timetableGenerationRun->subscription_id,
                $request->user()?->id,
                $payload,
                (bool) $timetableGenerationRun->is_preview,
                (int) $timetableGenerationRun->id
            )
            : $this->service->executeSingle(
                (int) $timetableGenerationRun->subscription_id,
                $request->user()?->id,
                $payload,
                (bool) $timetableGenerationRun->is_preview,
                (int) $timetableGenerationRun->id
            );

        return response()->json([
            'success' => true,
            'message' => 'Timetable generation retry completed.',
            'data' => $result,
        ], 201);
    }

    private function ensureOwned(Request $request, TimetableGenerationRun $run): void
    {
        abort_unless(
            (int) $run->subscription_id === $this->subscriptionId($request),
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
