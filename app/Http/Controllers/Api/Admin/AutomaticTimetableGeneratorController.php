<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\AcademicPlanning\AdvancedTimetableGeneratorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AutomaticTimetableGeneratorController extends Controller
{
    public function __construct(
        protected AdvancedTimetableGeneratorService $service
    ) {}

    public function constraints(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                [
                    'rule_key' => 'teacher.max_daily_periods',
                    'value_type' => 'integer',
                    'default' => null,
                    'constraint_type' => 'hard',
                    'description' => 'Maximum periods a teacher may teach in one day.',
                ],
                [
                    'rule_key' => 'teacher.max_weekly_periods',
                    'value_type' => 'integer',
                    'default' => null,
                    'constraint_type' => 'hard',
                    'description' => 'Maximum periods a teacher may teach in one week.',
                ],
                [
                    'rule_key' => 'teacher.max_consecutive_periods',
                    'value_type' => 'integer',
                    'default' => null,
                    'constraint_type' => 'hard',
                    'description' => 'Maximum consecutive teaching periods allowed for a teacher.',
                ],
                [
                    'rule_key' => 'subject.spread_across_days',
                    'value_type' => 'boolean',
                    'default' => true,
                    'constraint_type' => 'soft',
                    'description' => 'Prefer distributing a subject across different weekdays.',
                ],
                [
                    'rule_key' => 'class.blocked_slots',
                    'value_type' => 'json',
                    'default' => [],
                    'constraint_type' => 'hard_or_soft',
                    'description' => 'Blocked class slots. Each item requires weekday and school_bell_id.',
                    'item_schema' => [
                        'weekday' => 'integer:1-7',
                        'school_bell_id' => 'integer',
                    ],
                ],
                [
                    'rule_key' => 'teacher.blocked_slots',
                    'value_type' => 'json',
                    'default' => [],
                    'constraint_type' => 'hard_or_soft',
                    'description' => 'Blocked teacher slots.',
                    'item_schema' => [
                        'teacher_id' => 'integer',
                        'weekday' => 'integer:1-7',
                        'school_bell_id' => 'integer',
                    ],
                ],
                [
                    'rule_key' => 'subject.blocked_slots',
                    'value_type' => 'json',
                    'default' => [],
                    'constraint_type' => 'hard_or_soft',
                    'description' => 'Blocked subject slots.',
                    'item_schema' => [
                        'subject_id' => 'integer',
                        'weekday' => 'integer:1-7',
                        'school_bell_id' => 'integer',
                    ],
                ],
                [
                    'rule_key' => 'room.required_for_labs',
                    'value_type' => 'boolean',
                    'default' => true,
                    'constraint_type' => 'hard_or_soft',
                    'description' => 'Require compatible laboratory rooms for lab-category subjects.',
                ],
            ],
        ]);
    }

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