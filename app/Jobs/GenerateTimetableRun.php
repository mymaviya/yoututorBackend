<?php

namespace App\Jobs;

use App\Services\AcademicPlanning\TimetableGenerationRunService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GenerateTimetableRun implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 1800;

    public function __construct(
        public readonly int $generationRunId
    ) {
        $this->onQueue('timetables');
    }

    public function handle(TimetableGenerationRunService $service): void
    {
        $service->executeQueuedRun($this->generationRunId);
    }
}
