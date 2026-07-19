<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\AcademicPlanning\AutomaticTimetableGeneratorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AutomaticTimetableGeneratorController extends Controller
{
    public function __construct(
        protected AutomaticTimetableGeneratorService $service
    ) {}

    public function preview(Request $request): JsonResponse
    {
        $subscriptionId = $this->subscriptionId($request);
        $data = $this->validatedData($request, $subscriptionId);

        return response()->json([
            'success' => true,
            'message' => 'Timetable preview generated successfully.',
            'data' => $this->service->generate($subscriptionId, $data, true),
        ]);
    }

    public function generate(Request $request): JsonResponse
    {
        $subscriptionId = $this->subscriptionId($request);
        $data = $this->validatedData($request, $subscriptionId);

        return response()->json([
            'success' => true,
            'message' => 'Timetable generated successfully.',
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
            'grade_id' => [
                'required',
                'integer',
                Rule::exists('grades', 'id')->where(
                    fn ($query) => $query->where('subscription_id', $subscriptionId)
                ),
            ],
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
