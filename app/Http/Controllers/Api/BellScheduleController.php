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
        $bells = SchoolBell::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('start_time')
            ->get();

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

            'bus_dispersal_duration' => ['required', 'integer', 'min:1', 'max:120'],
            'teacher_dispersal_after_school_over' => ['required', 'integer', 'min:0', 'max:300'],

            'auto_calculate_period_duration' => ['required', 'boolean'],
            'first_period_duration' => ['nullable', 'integer', 'min:1', 'max:120'],
            'regular_period_duration' => ['nullable', 'integer', 'min:1', 'max:120'],

            'effective_from' => ['nullable', 'date'],
            'is_active' => ['boolean'],
        ]);

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
            'data' => SchoolBell::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('start_time')
                ->get(),
        ]);
    }

    public function preview(): JsonResponse
    {
        $bells = SchoolBell::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('start_time')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $bells,
        ]);
    }
}