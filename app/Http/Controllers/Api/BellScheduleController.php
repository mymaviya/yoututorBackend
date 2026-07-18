<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BellScheduleSetting;
use App\Models\SchoolBell;
use App\Services\BellScheduleGeneratorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BellScheduleController extends Controller
{
    public function index(): JsonResponse
    {
        $bells = $this->bellQuery()->get();

        return response()->json([
            'success' => true,
            'data' => $bells,
        ]);
    }

    public function settings(): JsonResponse
    {
        $setting = BellScheduleSetting::query()
            ->where('is_active', true)
            ->latest()
            ->first();

        return response()->json([
            'success' => true,
            'data' => $setting,
        ]);
    }

    public function saveSettings(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id' => ['nullable', 'exists:bell_schedule_settings,id'],
            'name' => ['required', 'string', 'max:255'],
            'assembly_bell_time' => ['required', 'date_format:H:i'],
            'school_over_time' => ['required', 'date_format:H:i', 'after:assembly_bell_time'],

            'total_periods' => ['required', 'integer', 'min:1', 'max:12'],

            'teacher_arrival_before_assembly' => ['required', 'integer', 'min:0', 'max:180'],
            'student_arrival_before_assembly' => ['required', 'integer', 'min:0', 'max:180'],
            'assembly_duration' => ['required', 'integer', 'min:1', 'max:120'],

            'break_mode' => ['required', 'in:none,short_only,long_only,short_and_long'],

            'short_break_after_period' => ['nullable', 'integer', 'min:1', 'max:12'],
            'short_break_duration' => ['required', 'integer', 'min:0', 'max:120'],

            'long_break_after_period' => ['nullable', 'integer', 'min:1', 'max:12'],
            'long_break_duration' => ['required', 'integer', 'min:0', 'max:120'],

            'first_period_extra_minutes' => ['required', 'integer', 'min:0', 'max:30'],
            'period_after_break_gap' => ['required', 'integer', 'min:0', 'max:30'],

            'bus_dispersal_enabled' => ['required', 'boolean'],
            'bus_dispersal_duration' => ['required', 'integer', 'min:1', 'max:120'],
            'teacher_dispersal_after_school_over' => ['required', 'integer', 'min:0', 'max:300'],

            'auto_calculate_period_duration' => ['required', 'boolean'],
            'first_period_duration' => ['nullable', 'integer', 'min:1', 'max:120'],
            'regular_period_duration' => ['nullable', 'integer', 'min:1', 'max:120'],

            'effective_from' => ['nullable', 'date'],
            'is_active' => ['boolean'],
        ]);

        $this->validateBreakRules($validated);

        DB::transaction(function () use (&$setting, $validated) {
            if (($validated['is_active'] ?? true) === true) {
                BellScheduleSetting::query()->update([
                    'is_active' => false,
                ]);
            }

            $setting = BellScheduleSetting::updateOrCreate(
                ['id' => $validated['id'] ?? null],
                $validated
            );
        });

        return response()->json([
            'success' => true,
            'message' => 'Bell schedule settings saved successfully.',
            'data' => $setting,
        ]);
    }

    public function generate(BellScheduleGeneratorService $service): JsonResponse
    {
        $setting = BellScheduleSetting::query()
            ->where('is_active', true)
            ->latest()
            ->first();

        if (!$setting) {
            return response()->json([
                'success' => false,
                'message' => 'No active bell schedule setting found.',
            ], 422);
        }

        $service->generate($setting);

        return response()->json([
            'success' => true,
            'message' => 'Bell schedule generated successfully.',
            'data' => $this->bellQuery()->get(),
        ]);
    }

    public function preview(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->bellQuery()->get(),
        ]);
    }

    private function bellQuery()
    {
        return SchoolBell::query()
            ->where('is_active', true)
            ->orderBy('start_time')
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    private function validateBreakRules(array $validated): void
    {
        $totalPeriods = (int) $validated['total_periods'];
        $breakMode = $validated['break_mode'];

        if (in_array($breakMode, ['short_only', 'short_and_long'], true)) {
            validator($validated, [
                'short_break_after_period' => ['required', 'integer', 'min:1', 'max:' . max(1, $totalPeriods - 1)],
            ])->validate();
        }

        if (in_array($breakMode, ['long_only', 'short_and_long'], true)) {
            validator($validated, [
                'long_break_after_period' => ['required', 'integer', 'min:1', 'max:' . max(1, $totalPeriods - 1)],
            ])->validate();
        }

        if ($breakMode === 'short_and_long'
            && isset($validated['short_break_after_period'], $validated['long_break_after_period'])
            && (int) $validated['short_break_after_period'] === (int) $validated['long_break_after_period']) {
            validator([], [
                'short_break_after_period' => ['required'],
            ], [
                'short_break_after_period.required' => 'Short break and long break cannot be after the same period.',
            ])->validate();
        }
    }
}
