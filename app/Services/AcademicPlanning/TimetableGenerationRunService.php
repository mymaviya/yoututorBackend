<?php

namespace App\Services\AcademicPlanning;

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
        return $this->execute(
            subscriptionId: $subscriptionId,
            userId: $userId,
            mode: 'single',
            payload: $payload,
            preview: $preview,
            requestedItems: 1,
            parentRunId: $parentRunId,
            runner: fn () => $this->singleGenerator->generate(
                $subscriptionId,
                $payload,
                $preview
            )
        );
    }

    public function executeBatch(
        int $subscriptionId,
        ?int $userId,
        array $payload,
        bool $preview,
        ?int $parentRunId = null
    ): array {
        return $this->execute(
            subscriptionId: $subscriptionId,
            userId: $userId,
            mode: 'batch',
            payload: $payload,
            preview: $preview,
            requestedItems: count($payload['classes'] ?? []),
            parentRunId: $parentRunId,
            runner: fn () => $this->batchGenerator->generate(
                $subscriptionId,
                $payload,
                $preview
            )
        );
    }

    private function execute(
        int $subscriptionId,
        ?int $userId,
        string $mode,
        array $payload,
        bool $preview,
        int $requestedItems,
        ?int $parentRunId,
        callable $runner
    ): array {
        $run = TimetableGenerationRun::query()->create([
            'subscription_id' => $subscriptionId,
            'user_id' => $userId,
            'parent_run_id' => $parentRunId,
            'mode' => $mode,
            'is_preview' => $preview,
            'status' => 'running',
            'progress_percentage' => 5,
            'requested_items' => max(1, $requestedItems),
            'started_at' => now(),
            'request_payload' => $payload,
        ]);

        try {
            $result = $runner();
            $successful = $mode === 'batch'
                ? (int) ($result['successful_classes'] ?? 0)
                : ((int) ($result['unscheduled_periods'] ?? 0) === 0 ? 1 : 0);
            $failed = $mode === 'batch'
                ? (int) ($result['failed_classes'] ?? 0)
                : ($successful === 1 ? 0 : 1);
            $processed = $mode === 'batch'
                ? $successful + $failed
                : 1;
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

            $this->storeResultConflicts($run, $result, $mode);

            return array_merge($result, [
                'generation_run_id' => $run->id,
                'generation_run_status' => $run->status,
            ]);
        } catch (Throwable $exception) {
            $run->forceFill([
                'status' => 'failed',
                'progress_percentage' => 100,
                'processed_items' => max(1, $requestedItems),
                'failed_items' => max(1, $requestedItems),
                'error_message' => $exception->getMessage(),
                'completed_at' => now(),
            ])->save();

            $this->storeExceptionConflicts($run, $exception);
            throw $exception;
        }
    }

    private function summary(array $result): array
    {
        return Arr::only($result, [
            'preview',
            'atomic',
            'requested_classes',
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
