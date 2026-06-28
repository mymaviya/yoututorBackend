<?php

namespace App\Jobs;

use App\Models\AiPaperGeneration;
use App\Services\AiPaperGeneratorService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class GenerateAiPaperJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 900;

    public function __construct(
        public int $generationId
    ) {}

    public function handle(AiPaperGeneratorService $service): void
    {
        $generation = AiPaperGeneration::findOrFail($this->generationId);

        $service->generate($generation);
    }
}