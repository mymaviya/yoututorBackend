<?php

namespace App\Services;

use App\Models\BellScheduleSetting;
use App\Models\SchoolBell;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class BellScheduleGeneratorService
{
    public function generate(
        int $subscriptionId,
        ?BellScheduleSetting $setting = null
    ): void {
        $setting ??= BellScheduleSetting::query()
            ->forSubscription($subscriptionId)
            ->active()
            ->latest('id')
            ->first();

        if (! $setting) {
            throw new InvalidArgumentException(
                'No active bell schedule setting found.'
            );
        }

        if ((int) $setting->subscription_id !== $subscriptionId) {
            throw new InvalidArgumentException(
                'The selected bell schedule setting does not belong to this subscription.'
            );
        }

        DB::transaction(function () use ($setting, $subscriptionId): void {
            SchoolBell::query()
                ->forSubscription($subscriptionId)
                ->delete();

            $assemblyStart = Carbon::parse($setting->assembly_bell_time);
            $schoolOver = Carbon::parse($setting->school_over_time);

            if ($schoolOver->lessThanOrEqualTo($assemblyStart)) {
                throw new InvalidArgumentException(
                    'School over time must be after assembly bell time.'
                );
            }

            $sortOrder = 1;

            $this->createBell(
                $subscriptionId,
                $sortOrder++,
                'Teacher Arrival',
                'teacher_arrival',
                $assemblyStart->copy()->subMinutes(
                    $setting->teacher_arrival_before_assembly
                ),
                $setting->teacher_arrival_before_assembly
            );

            $this->createBell(
                $subscriptionId,
                $sortOrder++,
                'Student Arrival',
                'student_arrival',
                $assemblyStart->copy()->subMinutes(
                    $setting->student_arrival_before_assembly
                ),
                $setting->student_arrival_before_assembly
            );

            $current = $assemblyStart->copy();

            $this->createBell(
                $subscriptionId,
                $sortOrder++,
                'Assembly',
                'assembly',
                $current,
                $setting->assembly_duration
            );
            $current->addMinutes($setting->assembly_duration);

            [$firstDuration, $regularDuration] = $this->resolvePeriodDurations(
                $setting,
                $current,
                $schoolOver
            );

            for ($period = 1; $period <= $setting->total_periods; $period++) {
                $duration = $period === 1
                    ? $firstDuration
                    : $regularDuration;

                $this->createBell(
                    $subscriptionId,
                    $sortOrder++,
                    'Period ' . $period,
                    'period',
                    $current,
                    $duration,
                    $period,
                    true
                );

                $current->addMinutes($duration);

                if ($this->shouldAddShortBreak($setting, $period)) {
                    $this->createBell(
                        $subscriptionId,
                        $sortOrder++,
                        'Short Break',
                        'short_break',
                        $current,
                        $setting->short_break_duration,
                        null,
                        false,
                        true
                    );

                    $current->addMinutes($setting->short_break_duration);
                    $current = $this->addPeriodStartGap(
                        $setting,
                        $current,
                        $subscriptionId,
                        $sortOrder
                    );
                    $sortOrder++;
                }

                if ($this->shouldAddLongBreak($setting, $period)) {
                    $this->createBell(
                        $subscriptionId,
                        $sortOrder++,
                        'Long Break',
                        'long_break',
                        $current,
                        $setting->long_break_duration,
                        null,
                        false,
                        true
                    );

                    $current->addMinutes($setting->long_break_duration);
                    $current = $this->addPeriodStartGap(
                        $setting,
                        $current,
                        $subscriptionId,
                        $sortOrder
                    );
                    $sortOrder++;
                }
            }

            if ($current->greaterThan($schoolOver)) {
                throw new InvalidArgumentException(
                    'Generated periods exceed the configured school over time.'
                );
            }

            if ($current->lessThan($schoolOver)) {
                $this->createBell(
                    $subscriptionId,
                    $sortOrder++,
                    'Buffer / Activity Time',
                    'other',
                    $current,
                    $current->diffInMinutes($schoolOver)
                );
            }

            $this->createBell(
                $subscriptionId,
                $sortOrder++,
                'All Students Dispersal',
                'student_dispersal',
                $schoolOver,
                1,
                null,
                false,
                false,
                true
            );

            if ($setting->bus_dispersal_enabled) {
                $this->createBell(
                    $subscriptionId,
                    $sortOrder++,
                    'Bus Students Dispersal',
                    'bus_dispersal',
                    $schoolOver,
                    $setting->bus_dispersal_duration,
                    null,
                    false,
                    false,
                    true
                );
            }

            $teacherDispersal = $schoolOver->copy()->addMinutes(
                $setting->teacher_dispersal_after_school_over
            );

            $this->createBell(
                $subscriptionId,
                $sortOrder,
                'Teacher Dispersal',
                'teacher_dispersal',
                $teacherDispersal,
                1,
                null,
                false,
                false,
                true
            );
        });
    }

    private function resolvePeriodDurations(
        BellScheduleSetting $setting,
        Carbon $periodStartTime,
        Carbon $schoolOver
    ): array {
        $extra = $setting->first_period_extra_minutes ?? 5;

        if (! $setting->auto_calculate_period_duration) {
            $first = $setting->first_period_duration
                ?? (($setting->regular_period_duration ?? 40) + $extra);
            $regular = $setting->regular_period_duration ?? 40;

            return [(int) $first, (int) $regular];
        }

        $availableMinutes = $periodStartTime->diffInMinutes(
            $schoolOver,
            false
        );
        $breakMinutes = 0;

        if (in_array(
            $setting->break_mode,
            ['short_only', 'short_and_long'],
            true
        )) {
            $breakMinutes += $setting->short_break_duration
                + $setting->period_after_break_gap;
        }

        if (in_array(
            $setting->break_mode,
            ['long_only', 'short_and_long'],
            true
        )) {
            $breakMinutes += $setting->long_break_duration
                + $setting->period_after_break_gap;
        }

        $teachingMinutes = $availableMinutes - $breakMinutes;

        if ($teachingMinutes <= 0) {
            throw new InvalidArgumentException(
                'School timing is too short for selected breaks.'
            );
        }

        $regular = intdiv(
            $teachingMinutes - $extra,
            $setting->total_periods
        );

        if ($regular <= 0) {
            throw new InvalidArgumentException(
                'Period duration could not be calculated.'
            );
        }

        return [$regular + $extra, $regular];
    }

    private function addPeriodStartGap(
        BellScheduleSetting $setting,
        Carbon $current,
        int $subscriptionId,
        int $sortOrder
    ): Carbon {
        if ($setting->period_after_break_gap <= 0) {
            return $current;
        }

        $this->createBell(
            $subscriptionId,
            $sortOrder,
            'Period Start Gap',
            'other',
            $current,
            $setting->period_after_break_gap
        );

        return $current->copy()->addMinutes(
            $setting->period_after_break_gap
        );
    }

    private function shouldAddShortBreak(
        BellScheduleSetting $setting,
        int $period
    ): bool {
        return in_array(
            $setting->break_mode,
            ['short_only', 'short_and_long'],
            true
        )
            && $setting->short_break_after_period
            && $period === (int) $setting->short_break_after_period;
    }

    private function shouldAddLongBreak(
        BellScheduleSetting $setting,
        int $period
    ): bool {
        return in_array(
            $setting->break_mode,
            ['long_only', 'short_and_long'],
            true
        )
            && $setting->long_break_after_period
            && $period === (int) $setting->long_break_after_period;
    }

    private function createBell(
        int $subscriptionId,
        int $sortOrder,
        string $title,
        string $type,
        Carbon $start,
        int $duration,
        ?int $periodNumber = null,
        bool $isTeachingPeriod = false,
        bool $isBreak = false,
        bool $isDispersal = false
    ): void {
        SchoolBell::query()->create([
            'subscription_id' => $subscriptionId,
            'title' => $title,
            'type' => $type,
            'start_time' => $start->format('H:i:s'),
            'duration_minutes' => $duration,
            'end_time' => $start->copy()
                ->addMinutes($duration)
                ->format('H:i:s'),
            'period_number' => $periodNumber,
            'is_teaching_period' => $isTeachingPeriod,
            'is_break' => $isBreak,
            'is_dispersal' => $isDispersal,
            'is_active' => true,
            'sort_order' => $sortOrder,
        ]);
    }
}
