<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BellScheduleSetting;
use App\Models\SchoolBell;
use App\Services\BellScheduleGeneratorService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class BellScheduleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $subscriptionId = $this->subscriptionId($request);

        return response()->json([
            'success' => true,
            'data' => $this->bellQuery($subscriptionId)->get(),
        ]);
    }

    public function settings(Request $request): JsonResponse
    {
        $subscriptionId = $this->subscriptionId($request);

        $setting = BellScheduleSetting::query()
            ->forSubscription($subscriptionId)
            ->active()
            ->latest('id')
            ->first();

        return response()->json([
            'success' => true,
            'data' => $setting,
        ]);
    }

    public function saveSettings(Request $request): JsonResponse
    {
        $subscriptionId = $this->subscriptionId($request);

        $validated = $request->validate([
            'id' => [
                'nullable',
                'integer',
                Rule::exists('bell_schedule_settings', 'id')
                    ->where('subscription_id', $subscriptionId),
            ],
            'name' => ['required', 'string', 'max:255'],
            'assembly_bell_time' => ['required', 'date_format:H:i'],
            'school_over_time' => [
                'required',
                'date_format:H:i',
                'after:assembly_bell_time',
            ],
            'total_periods' => ['required', 'integer', 'min:1', 'max:12'],
            'teacher_arrival_before_assembly' => [
                'required',
                'integer',
                'min:0',
                'max:180',
            ],
            'student_arrival_before_assembly' => [
                'required',
                'integer',
                'min:0',
                'max:180',
            ],
            'assembly_duration' => [
                'required',
                'integer',
                'min:1',
                'max:120',
            ],
            'break_mode' => [
                'required',
                Rule::in([
                    'none',
                    'short_only',
                    'long_only',
                    'short_and_long',
                ]),
            ],
            'short_break_after_period' => [
                'nullable',
                'integer',
                'min:1',
                'max:12',
            ],
            'short_break_duration' => [
                'required',
                'integer',
                'min:0',
                'max:120',
            ],
            'long_break_after_period' => [
                'nullable',
                'integer',
                'min:1',
                'max:12',
            ],
            'long_break_duration' => [
                'required',
                'integer',
                'min:0',
                'max:120',
            ],
            'first_period_extra_minutes' => [
                'required',
                'integer',
                'min:0',
                'max:30',
            ],
            'period_after_break_gap' => [
                'required',
                'integer',
                'min:0',
                'max:30',
            ],
            'bus_dispersal_enabled' => ['required', 'boolean'],
            'bus_dispersal_duration' => [
                Rule::requiredIf(
                    fn (): bool => $request->boolean(
                        'bus_dispersal_enabled'
                    )
                ),
                'nullable',
                'integer',
                'min:1',
                'max:120',
            ],
            'teacher_dispersal_after_school_over' => [
                'required',
                'integer',
                'min:0',
                'max:300',
            ],
            'auto_calculate_period_duration' => ['required', 'boolean'],
            'first_period_duration' => [
                Rule::requiredIf(
                    fn (): bool => ! $request->boolean(
                        'auto_calculate_period_duration'
                    )
                ),
                'nullable',
                'integer',
                'min:1',
                'max:120',
            ],
            'regular_period_duration' => [
                Rule::requiredIf(
                    fn (): bool => ! $request->boolean(
                        'auto_calculate_period_duration'
                    )
                ),
                'nullable',
                'integer',
                'min:1',
                'max:120',
            ],
            'effective_from' => ['nullable', 'date'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $this->validateBreakRules($validated);

        $validated['subscription_id'] = $subscriptionId;
        $validated['is_active'] = $validated['is_active'] ?? true;

        $setting = DB::transaction(function () use (
            $validated,
            $subscriptionId
        ): BellScheduleSetting {
            if ($validated['is_active']) {
                BellScheduleSetting::query()
                    ->forSubscription($subscriptionId)
                    ->update(['is_active' => false]);
            }

            if (! empty($validated['id'])) {
                $setting = BellScheduleSetting::query()
                    ->forSubscription($subscriptionId)
                    ->findOrFail((int) $validated['id']);

                $setting->update($validated);

                return $setting->refresh();
            }

            return BellScheduleSetting::query()->create($validated);
        });

        return response()->json([
            'success' => true,
            'message' => 'Bell schedule settings saved successfully.',
            'data' => $setting,
        ]);
    }

    public function generate(
        Request $request,
        BellScheduleGeneratorService $service
    ): JsonResponse {
        $subscriptionId = $this->subscriptionId($request);

        $setting = BellScheduleSetting::query()
            ->forSubscription($subscriptionId)
            ->active()
            ->latest('id')
            ->first();

        if (! $setting) {
            return response()->json([
                'success' => false,
                'message' => 'No active bell schedule setting found.',
            ], 422);
        }

        $service->generate($subscriptionId, $setting);

        return response()->json([
            'success' => true,
            'message' => 'Bell schedule generated successfully.',
            'data' => $this->bellQuery($subscriptionId)->get(),
        ]);
    }

    public function preview(Request $request): JsonResponse
    {
        $subscriptionId = $this->subscriptionId($request);

        return response()->json([
            'success' => true,
            'data' => $this->bellQuery($subscriptionId)->get(),
        ]);
    }

    private function bellQuery(int $subscriptionId): Builder
    {
        return SchoolBell::query()
            ->forSubscription($subscriptionId)
            ->active()
            ->ordered();
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

    private function validateBreakRules(array $validated): void
    {
        $totalPeriods = (int) $validated['total_periods'];
        $breakMode = $validated['break_mode'];
        $maxBreakPeriod = max(1, $totalPeriods - 1);

        if (in_array(
            $breakMode,
            ['short_only', 'short_and_long'],
            true
        )) {
            validator($validated, [
                'short_break_after_period' => [
                    'required',
                    'integer',
                    'min:1',
                    'max:' . $maxBreakPeriod,
                ],
                'short_break_duration' => ['required', 'integer', 'min:1'],
            ])->validate();
        }

        if (in_array(
            $breakMode,
            ['long_only', 'short_and_long'],
            true
        )) {
            validator($validated, [
                'long_break_after_period' => [
                    'required',
                    'integer',
                    'min:1',
                    'max:' . $maxBreakPeriod,
                ],
                'long_break_duration' => ['required', 'integer', 'min:1'],
            ])->validate();
        }

        if (
            $breakMode === 'short_and_long'
            && isset(
                $validated['short_break_after_period'],
                $validated['long_break_after_period']
            )
            && (int) $validated['short_break_after_period']
                === (int) $validated['long_break_after_period']
        ) {
            validator([], [
                'short_break_after_period' => ['required'],
            ], [
                'short_break_after_period.required' =>
                    'Short break and long break cannot be after the same period.',
            ])->validate();
        }
    }
}
