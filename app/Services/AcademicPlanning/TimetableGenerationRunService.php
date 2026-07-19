<?php

namespace App\Services\AcademicPlanning;

use App\Jobs\GenerateTimetableRun;
use App\Models\TimetableGenerationRun;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use Throwable;

class TimetableGenerationRunService
{
    public function __construct(
        protected AdvancedTimetableGeneratorService $singleGenerator,
        protected BatchTimetableGeneratorService $batchGenerator
    ) {}

    public function executeSingle(
        int $subscriptionId,
        ?int $userId,
        array $payload,
        bool $preview,
        ?int $parentRunId = null
    ): array {
        $run = $this->createRun(
            $subscriptionId,
            $userId,
            'single',
            $payload,
            $preview,
            1,
            $parentRunId,
            'running'
        );

        return $this->executeRun($run);
    }

    public function executeBatch(
        int $subscriptionId,
        ?int $userId,
        array $payload,
        bool $preview,
        ?int $parentRunId = null
    ): array {
        $run = $this->createRun(
            $subscriptionId,
            $userId,
            'batch',
            $payload,
            $preview,
            count($payload['classes'] ?? []),
            $parentRunId,
            'running'
        );

        return $this->executeRun($run);
    }

    public function queueSingle(
        int $subscriptionId,
        ?int $userId,
        array $payload,
        bool $preview,
        ?int $parentRunId = null
    ): TimetableGenerationRun {
        return $this->queue(
            $subscriptionId,
            $userId,
            'single',
            $payload,
            $preview,
            1,
            $parentRunId
        );
    }

    public function queueBatch(
        int $subscriptionId,
        ?int $userId,
        array $payload,
        bool $preview,
        ?int $parentRunId = null
    ): TimetableGenerationRun {
        return $this->queue(
            $subscriptionId,
            $userId,
            'batch',
            $payload,
            $preview,
            count($payload['classes'] ?? []),
            $parentRunId
        );
    }

    public function executeQueuedRun(int $runId): void
    {
        $run = TimetableGenerationRun::query()->findOrFail($runId);

        if ($run->isTerminal()) {
            return;
        }

        if ($run->cancellationRequested()) {
            $this->markCancelled($run);
            return;
        }

        $run->forceFill([
            'status' => 'running',
            'progress_percentage' => 1,
            'attempt_count' => (int) $run->attempt_count + 1,
            'started_at' => $run->started_at ?? now(),
            'completed_at' => null,
            'error_message' => null,
        ])->save();

        $this->executeRun($run, false);
    }

    public function requestCancellation(TimetableGenerationRun $run): TimetableGenerationRun
    {
        if (! $run->canBeCancelled()) {
            throw ValidationException::withMessages([
                'generation_run' => 'Only queued or running generation runs can be cancelled.',
            ]);
        }

        $run->forceFill([
            'cancellation_requested_at' => now(),
        ])->save();

        if ($run->status === 'queued') {
            $this->markCancelled($run);
        }

        return $run->fresh()->loadCount('conflicts');
    }

    private function queue(
        int $subscriptionId,
        ?int $userId,
        string $mode,
        array $payload,
        bool $preview,
        int $requestedItems,
        ?int $parentRunId
    ): TimetableGenerationRun {
        $run = $this->createRun(
            $subscriptionId,
            $userId,
            $mode,
            $payload,
            $preview,
            $requestedItems,
            $parentRunId,
            'queued'
        );

        GenerateTimetableRun::dispatch($run->id);

        return $run->fresh();
    }

    private function createRun(
        int $subscriptionId,
        ?int $userId,
        string $mode,
        array $payload,
        bool $preview,
        int $requestedItems,
        ?int $parentRunId,
        string $status
    ): TimetableGenerationRun {
        return TimetableGenerationRun::query()->create([
            'subscription_id' => $subscriptionId,
            'user_id' => $userId,
            'parent_run_id' => $parentRunId,
            'mode' => $mode,
            'is_preview' => $preview,
            'status' => $status,
            'progress_percentage' => $status === 'running' ? 5 : 0,
            'requested_items' => max(1, $requestedItems),
            'attempt_count' => $status === 'running' ? 1 : 0,
            'started_at' => $status === 'running' ? now() : null,
            'request_payload' => $payload,
        ]);
    }

    private function executeRun(
        TimetableGenerationRun $run,
        bool $returnResult = true
    ): array {
        try {
            $payload = (array) $run->request_payload;

            if ($run->mode === 'batch') {
                $result = $this->batchGenerator->generate(
                    (int) $run->subscription_id,
                    $payload,
                    (bool) $run->is_preview,
                    fn (array $progress) => $this->updateProgress($run, $progress),
                    fn () => $this->cancellationRequested($run)
                );
            } else {
                if ($this->cancellationRequested($run)) {
                    $this->markCancelled($run);
                    return [];
                }

                $run->forceFill(['progress_percentage' => 15])->save();
                $result = $this->singleGenerator->generate(
                    (int) $run->subscription_id,
                    $payload,
                    (bool) $run->is_preview
                );
            }

            $successful = $run->mode === 'batch'
                ? (int) ($result['successful_classes'] ?? 0)
                : ((int) ($result['unscheduled_periods'] ?? 0) === 0 ? 1 : 0);
            $failed = $run->mode === 'batch'
                ? (int) ($result['failed_classes'] ?? 0)
                : ($successful === 1 ? 0 : 1);
            $processed = $run->mode === 'batch'
                ? (int) ($result['processed_classes'] ?? ($successful + $failed))
                : 1;

            if (($result['cancelled'] ?? false) || $this->cancellationRequested($run)) {
                $run->forceFill([
                    'status' => 'cancelled',
                    'progress_percentage' => min(99, (int) ($run->progress_percentage ?? 0)),
                    'processed_items' => $processed,
                    'successful_items' => $successful,
                    'failed_items' => $failed,
                    'result_summary' => $this->summary($result),
                    'cancelled_at' => now(),
                    'completed_at' => now(),
                ])->save();
            } else {
                $status = $failed > 0
                    ? ($successful > 0 ? 'partial' : 'failed')
                    : 'completed';

                $run->forceFill([
                    'status' => $status,
                    'progress_percentage' => 100,
                    'processed_items' => $processed,
                    'successful_items' => $successful,
                    'failed_items' => $failed,
                    'result_summary' => $this->summary($result),
                    'completed_at' => now(),
                ])->save();
            }

            $this->storeResultConflicts($run, $result, $run->mode);

            return $returnResult
                ? array_merge($result, [
                    'generation_run_id' => $run->id,
                    'generation_run_status' => $run->status,
                ])
                : [];
        } catch (Throwable $exception) {
            $run->refresh();

            if ($run->cancellationRequested()) {
                $this->markCancelled($run, $exception->getMessage());
                return [];
            }

            $run->forceFill([
                'status' => 'failed',
                'progress_percentage' => 100,
                'processed_items' => max(1, (int) $run->processed_items),
                'failed_items' => max(1, (int) $run->requested_items),
                'error_message' => $exception->getMessage(),
                'completed_at' => now(),
            ])->save();

            $this->storeExceptionConflicts($run, $exception);

            if ($returnResult) {
                throw $exception;
            }

            report($exception);
            return [];
        }
    }

    private function updateProgress(TimetableGenerationRun $run, array $progress): void
    {
        $run->refresh();

        if ($run->isTerminal()) {
            return;
        }

        $run->forceFill([
            'progress_percentage' => max(
                (int) $run->progress_percentage,
                min(99, (int) ($progress['progress_percentage'] ?? 0))
            ),
            'processed_items' => (int) ($progress['processed_items'] ?? $run->processed_items),
            'successful_items' => (int) ($progress['successful_items'] ?? $run->successful_items),
            'failed_items' => (int) ($progress['failed_items'] ?? $run->failed_items),
        ])->save();
    }

    private function cancellationRequested(TimetableGenerationRun $run): bool
    {
        $run->refresh();
        return $run->cancellationRequested();
    }

    private function markCancelled(
        TimetableGenerationRun $run,
        ?string $message = null
    ): void {
        $run->forceFill([
            'status' => 'cancelled',
            'error_message' => $message,
            'cancelled_at' => now(),
            'completed_at' => now(),
        ])->save();
    }

    private function summary(array $result): array
    {
        return Arr::only($result, [
            'preview',
            'atomic',
            'cancelled',
            'requested_classes',
            'processed_classes',
            'successful_classes',
            'failed_classes',
            'requested_periods',
            'scheduled_periods',
            'unscheduled_periods',
            'completion_percentage',
            'weekly_timetable_id',
            'locked_entries_preserved',
        ]);
    }

    private function storeResultConflicts(
        TimetableGenerationRun $run,
        array $result,
        string $mode
    ): void {
        if ($mode === 'single') {
            foreach ((array) ($result['warnings'] ?? []) as $warning) {
                $run->conflicts()->create([
                    'conflict_type' => 'generation_warning',
                    'severity' => 'warning',
                    'message' => (string) $warning,
                    'context' => [],
                ]);
            }

            return;
        }

        foreach ((array) ($result['results'] ?? []) as $item) {
            $identity = (array) ($item['class'] ?? []);
            $index = isset($item['index']) ? (int) $item['index'] : null;

            foreach ((array) data_get($item, 'data.warnings', []) as $warning) {
                $run->conflicts()->create($this->conflictPayload(
                    $identity,
                    $index,
                    'generation_warning',
                    'warning',
                    (string) $warning,
                    []
                ));
            }

            if (! ($item['success'] ?? false)) {
                $run->conflicts()->create($this->conflictPayload(
                    $identity,
                    $index,
                    'class_generation_failed',
                    'error',
                    (string) ($item['message'] ?? 'Class timetable generation failed.'),
                    (array) ($item['errors'] ?? [])
                ));
            }
        }
    }

    private function storeExceptionConflicts(
        TimetableGenerationRun $run,
        Throwable $exception
    ): void {
        $errors = $exception instanceof ValidationException
            ? $exception->errors()
            : [];

        if ($errors === []) {
            $run->conflicts()->create([
                'conflict_type' => 'exception',
                'severity' => 'error',
                'message' => $exception->getMessage(),
                'context' => [],
            ]);

            return;
        }

        foreach ($errors as $field => $messages) {
            foreach ((array) $messages as $message) {
                $run->conflicts()->create([
                    'conflict_type' => 'validation',
                    'severity' => 'error',
                    'message' => (string) $message,
                    'context' => ['field' => $field],
                ]);
            }
        }
    }

    private function conflictPayload(
        array $identity,
        ?int $index,
        string $type,
        string $severity,
        string $message,
        array $context
    ): array {
        return [
            'item_index' => $index,
            'conflict_type' => $type,
            'severity' => $severity,
            'grade_id' => $identity['grade_id'] ?? null,
            'section_id' => $identity['section_id'] ?? null,
            'stream_id' => $identity['stream_id'] ?? null,
            'message' => $message,
            'context' => $context,
        ];
    }
}
