<?php

namespace Tests\Unit\Timetable;

use App\Models\TimetableGenerationRun;
use App\Models\WeeklyTimetable;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class TimetableStateTest extends TestCase
{
    #[DataProvider('weeklyTimetableStatusProvider')]
    public function test_weekly_timetable_lifecycle_helpers(
        string $status,
        bool $isDraft,
        bool $isPublished,
        bool $isArchived
    ): void {
        $timetable = new WeeklyTimetable();
        $timetable->status = $status;

        $this->assertSame($isDraft, $timetable->isDraft());
        $this->assertSame($isPublished, $timetable->isPublished());
        $this->assertSame($isArchived, $timetable->isArchived());
    }

    public static function weeklyTimetableStatusProvider(): array
    {
        return [
            'draft' => [WeeklyTimetable::STATUS_DRAFT, true, false, false],
            'published' => [WeeklyTimetable::STATUS_PUBLISHED, false, true, false],
            'archived' => [WeeklyTimetable::STATUS_ARCHIVED, false, false, true],
        ];
    }

    #[DataProvider('terminalRunStatusProvider')]
    public function test_generation_run_terminal_states(string $status, bool $terminal): void
    {
        $run = new TimetableGenerationRun();
        $run->status = $status;

        $this->assertSame($terminal, $run->isTerminal());
    }

    public static function terminalRunStatusProvider(): array
    {
        return [
            'queued' => ['queued', false],
            'running' => ['running', false],
            'completed' => ['completed', true],
            'partial' => ['partial', true],
            'failed' => ['failed', true],
            'cancelled' => ['cancelled', true],
        ];
    }

    #[DataProvider('cancellableRunStatusProvider')]
    public function test_generation_run_cancellation_rules(
        string $status,
        bool $requested,
        bool $expected
    ): void {
        $run = new TimetableGenerationRun();
        $run->status = $status;
        $run->cancellation_requested_at = $requested ? Carbon::now() : null;

        $this->assertSame($expected, $run->canBeCancelled());
        $this->assertSame($requested, $run->cancellationRequested());
    }

    public static function cancellableRunStatusProvider(): array
    {
        return [
            'queued' => ['queued', false, true],
            'running' => ['running', false, true],
            'queued already requested' => ['queued', true, false],
            'running already requested' => ['running', true, false],
            'completed' => ['completed', false, false],
            'partial' => ['partial', false, false],
            'failed' => ['failed', false, false],
            'cancelled' => ['cancelled', false, false],
        ];
    }

    public function test_status_and_mode_contracts_remain_complete(): void
    {
        $this->assertSame(
            ['draft', 'published', 'archived'],
            WeeklyTimetable::STATUSES
        );

        $this->assertSame(
            ['queued', 'running', 'completed', 'partial', 'failed', 'cancelled'],
            TimetableGenerationRun::STATUSES
        );

        $this->assertSame(['single', 'batch'], TimetableGenerationRun::MODES);
    }
}
