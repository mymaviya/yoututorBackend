<?php

namespace App\Services;

use App\Models\SchoolBell;
use App\Models\SchoolNotice;
use Carbon\Carbon;
use Illuminate\Http\Request;

class DesktopDashboardService
{
    public function getData(Request $request): array
    {
        $now = Carbon::now();

        return [
            'success' => true,
            'date' => $now->format('l, d F Y'),
            'time' => $now->format('h:i:s A'),

            'school' => $this->school(),

            'current_bell' => $this->currentBell($now),
            'next_bell' => $this->nextBell($now),
            'remaining_bells' => $this->remainingBells($now),

            'notices' => $this->notices(),
        ];
    }

    private function school(): array
    {
        return [
            'name' => config('app.name', 'YouTutor ERP'),
            'tagline' => 'Excellence in Education',
            'logo' => null,
        ];
    }

    private function currentBell(Carbon $now): ?array
    {
        $time = $now->format('H:i:s');

        $bell = SchoolBell::query()
            ->where('is_active', true)
            ->where('start_time', '<=', $time)
            ->where('end_time', '>=', $time)
            ->orderBy('sort_order')
            ->first();

        return $bell ? $this->formatBell($bell) : null;
    }

    private function nextBell(Carbon $now): ?array
    {
        $time = $now->format('H:i:s');

        $bell = SchoolBell::query()
            ->where('is_active', true)
            ->where('start_time', '>', $time)
            ->orderBy('start_time')
            ->first();

        return $bell ? $this->formatBell($bell) : null;
    }

    private function remainingBells(Carbon $now): array
    {
        $time = $now->format('H:i:s');

        return SchoolBell::query()
            ->where('is_active', true)
            ->where('start_time', '>', $time)
            ->orderBy('start_time')
            ->limit(6)
            ->get()
            ->map(fn ($bell) => $this->formatBell($bell))
            ->toArray();
    }

    private function notices(): array
    {
        return SchoolNotice::query()
            ->where('is_active', true)
            ->where('show_on_dashboard', true)
            ->where(function ($q) {
                $q->whereNull('start_date')
                    ->orWhereDate('start_date', '<=', today());
            })
            ->where(function ($q) {
                $q->whereNull('end_date')
                    ->orWhereDate('end_date', '>=', today());
            })
            ->orderByDesc('priority')
            ->latest()
            ->limit(5)
            ->get()
            ->map(fn ($notice) => [
                'id' => $notice->id,
                'title' => $notice->title,
                'description' => $notice->description,
                'icon' => $notice->icon ?: '📢',
                'date' => optional($notice->created_at)->format('d M'),
            ])
            ->toArray();
    }

    private function formatBell(SchoolBell $bell): array
    {
        return [
            'id' => $bell->id,
            'title' => $bell->title,
            'type' => $bell->type,
            'start_time' => $bell->start_time,
            'end_time' => $bell->end_time,
            'duration_minutes' => $bell->duration_minutes,
            'time' => Carbon::parse($bell->start_time)->format('h:i A'),
            'end_time_display' => Carbon::parse($bell->end_time)->format('h:i A'),
            'raw' => $bell->start_time,
            'is_teaching_period' => $bell->is_teaching_period,
            'is_break' => $bell->is_break,
            'is_dispersal' => $bell->is_dispersal,
        ];
    }
}