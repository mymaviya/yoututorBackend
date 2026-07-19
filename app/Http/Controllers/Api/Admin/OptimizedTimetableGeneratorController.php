<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\AcademicPlanning\OptimizedTimetableGeneratorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OptimizedTimetableGeneratorController extends Controller
{
    public function __construct(
        protected OptimizedTimetableGeneratorService $service
    ) {}

    public function preview(Request $request): JsonResponse
    {
        $subscriptionId = $this->subscriptionId($request);
        $data = $this->validatedData($request, $subscriptionId);

        return response()->json([
            'success' => true,
            'message' => 'Optimized timetable preview generated successfully.',
            'data' => $this->service->generate($subscriptionId, $data, true),
        ]);
    }

    public function generate(Request $request): JsonResponse
    {
        $subscriptionId = $this->subscriptionId($request);
        $data = $this->validatedData($request, $subscriptionId);

        return response()->json([
            'success' => true,
            'message' => 'Optimized timetable generated successfully.',
            'data' => $this->service->generate($subscriptionId, $data, false),
        ], 201);
    }

    private function validatedData(Request $request, int $subscriptionId): array
    {
        return $request->validate([
            'weekly_timetable_id' => [
                'nullable',
                'integer',
                Rule::exists('weekly_timetables', 'id')->where(
                    fn ($query) => $query->where('subscription_id', $subscriptionId)
                ),
            ],
            'academic_year_id' => [
                'nullable',
                'integer',
                Rule::exists('academic_years', 'id')->where(
                    fn ($query) => $query->where('subscription_id', $subscriptionId)
                ),
            ],
            'grade_id' => ['required', 'integer', 'exists:grades,id'],
            'section_id' => [
                'nullable',
                'integer',
                Rule::exists('sections', 'id')->where(
                    fn ($query) => $query->where('subscription_id', $subscriptionId)
                ),
            ],
            'stream_id' => [
                'nullable',
                'integer',
                Rule::exists('streams', 'id')->where(
                    fn ($query) => $query->where('subscription_id', $subscriptionId)
                ),
            ],
            'timetable_template_id' => [
                'required',
                'integer',
                Rule::exists('timetable_templates', 'id')->where(
                    fn ($query) => $query
                        ->where('subscription_id', $subscriptionId)
                        ->where('is_active', true)
                ),
            ],
            'name' => ['nullable', 'string', 'max:150'],
            'effective_from' => ['nullable', 'date'],
            'working_days' => ['nullable', 'integer', 'min:1', 'max:7'],
            'allow_partial' => ['nullable', 'boolean'],
            'optimization_attempts' => ['nullable', 'integer', 'min:1', 'max:10'],
        ]);
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
