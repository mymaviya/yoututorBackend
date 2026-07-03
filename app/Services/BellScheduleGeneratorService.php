<?php

namespace App\Services;

use App\Models\BellScheduleSetting;
use App\Models\SchoolBell;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class BellScheduleGeneratorService
{
    public function generate(?BellScheduleSetting $setting = null): void
    {
        $setting ??= BellScheduleSetting::where('is_active', true)->latest()->first();

        if (!$setting) {
            throw new InvalidArgumentException('No active bell schedule setting found.');
        }

        DB::transaction(function () use ($setting) {
            SchoolBell::query()->delete();

            $assemblyStart = Carbon::parse($setting->assembly_bell_time);
            $schoolOver = Carbon::parse($setting->school_over_time);

            if ($schoolOver->lessThanOrEqualTo($assemblyStart)) {
                throw new InvalidArgumentException('School over time must be after assembly bell time.');
            }

            $this->createBell(
                title: 'Teacher Arrival',
                type: 'teacher_arrival',
                start: $assemblyStart->copy()->subMinutes($setting->teacher_arrival_before_assembly),
                duration: $setting->teacher_arrival_before_assembly,
                sortOrder: 1
            );

            $this->createBell(
                title: 'Student Arrival',
                type: 'student_arrival',
                start: $assemblyStart->copy()->subMinutes($setting->student_arrival_before_assembly),
                duration: $setting->student_arrival_before_assembly,
                sortOrder: 2
            );

            $current = $assemblyStart->copy();

            $this->createBell(
                title: 'Assembly',
                type: 'assembly',
                start: $current,
                duration: $setting->assembly_duration,
                sortOrder: 3
            );

            $current->addMinutes($setting->assembly_duration);

            [$firstPeriodDuration, $regularPeriodDuration] =
                $this->resolvePeriodDurations($setting, $current, $schoolOver);

            for ($period = 1; $period <= $setting->total_periods; $period++) {
                $duration = $period === 1
                    ? $firstPeriodDuration
                    : $regularPeriodDuration;

                $this->createBell(
                    title: 'Period ' . $period,
                    type: 'period',
                    start: $current,
                    duration: $duration,
                    sortOrder: 10 + $period,
                    periodNumber: $period,
                    isTeachingPeriod: true
                );

                $current->addMinutes($duration);

                if ($this->shouldAddShortBreak($setting, $period)) {
                    $this->createBell(
                        title: 'Short Break',
                        type: 'short_break',
                        start: $current,
                        duration: $setting->short_break_duration,
                        sortOrder: 40 + $period,
                        isBreak: true
                    );

                    $current->addMinutes($setting->short_break_duration);
                }

                if ($this->shouldAddLongBreak($setting, $period)) {
                    $this->createBell(
                        title: 'Long Break',
                        type: 'long_break',
                        start: $current,
                        duration: $setting->long_break_duration,
                        sortOrder: 50 + $period,
                        isBreak: true
                    );

                    $current->addMinutes($setting->long_break_duration);
                }
            }

            $remainingMinutes = $current->diffInMinutes($schoolOver, false);

            if ($remainingMinutes > 0) {
                $this->createBell(
                    title: 'Buffer / Activity Time',
                    type: 'other',
                    start: $current,
                    duration: $remainingMinutes,
                    sortOrder: 80
                );

                $current->addMinutes($remainingMinutes);
            }

            $this->createBell(
                title: 'All Students Dispersal',
                type: 'student_dispersal',
                start: $schoolOver,
                duration: $setting->bus_dispersal_duration,
                sortOrder: 90,
                isDispersal: true
            );

            $this->createBell(
                title: 'Bus Students Dispersal',
                type: 'bus_dispersal',
                start: $schoolOver,
                duration: $setting->bus_dispersal_duration,
                sortOrder: 91,
                isDispersal: true
            );

            $teacherDispersal = $schoolOver->copy()
                ->addMinutes($setting->teacher_dispersal_after_school_over);

            $this->createBell(
                title: 'Teacher Dispersal',
                type: 'teacher_dispersal',
                start: $teacherDispersal,
                duration: 1,
                sortOrder: 100,
                isDispersal: true
            );
        });
    }

    private function resolvePeriodDurations(
        BellScheduleSetting $setting,
        Carbon $periodStartTime,
        Carbon $schoolOver
    ): array {
        if (!$setting->auto_calculate_period_duration) {
            return [
                $setting->first_period_duration ?? 45,
                $setting->regular_period_duration ?? 40,
            ];
        }

        $availableMinutes = $periodStartTime->diffInMinutes($schoolOver, false);

        $breakMinutes = 0;

        if (in_array($setting->break_mode, ['short_only', 'short_and_long'], true)) {
            $breakMinutes += $setting->short_break_duration;
        }

        if (in_array($setting->break_mode, ['long_only', 'short_and_long'], true)) {
            $breakMinutes += $setting->long_break_duration;
        }

        $teachingMinutes = $availableMinutes - $breakMinutes;

        if ($teachingMinutes <= 0) {
            throw new InvalidArgumentException('School timing is too short for selected breaks.');
        }

        if ($setting->total_periods <= 0) {
            throw new InvalidArgumentException('Total periods must be greater than zero.');
        }

        $duration = intdiv($teachingMinutes, $setting->total_periods);

        if ($duration <= 0) {
            throw new InvalidArgumentException('Period duration could not be calculated.');
        }

        return [$duration, $duration];
    }

    private function shouldAddShortBreak(BellScheduleSetting $setting, int $period): bool
    {
        return in_array($setting->break_mode, ['short_only', 'short_and_long'], true)
            && $setting->short_break_after_period
            && $period === $setting->short_break_after_period;
    }

    private function shouldAddLongBreak(BellScheduleSetting $setting, int $period): bool
    {
        return in_array($setting->break_mode, ['long_only', 'short_and_long'], true)
            && $setting->long_break_after_period
            && $period === $setting->long_break_after_period;
    }

    private function createBell(
        string $title,
        string $type,
        Carbon $start,
        int $duration,
        int $sortOrder,
        ?int $periodNumber = null,
        bool $isTeachingPeriod = false,
        bool $isBreak = false,
        bool $isDispersal = false
    ): void {
        SchoolBell::create([
            'title' => $title,
            'type' => $type,
            'start_time' => $start->format('H:i:s'),
            'duration_minutes' => $duration,
            'end_time' => $start->copy()->addMinutes($duration)->format('H:i:s'),
            'period_number' => $periodNumber,
            'is_teaching_period' => $isTeachingPeriod,
            'is_break' => $isBreak,
            'is_dispersal' => $isDispersal,
            'is_active' => true,
            'sort_order' => $sortOrder,
        ]);
    }
}