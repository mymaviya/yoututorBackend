<?php

namespace App\Services\AcademicPlanning;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class BatchTimetableGeneratorService
{
    public function __construct(
        protected AdvancedTimetableGeneratorService $generator
    ) {}

    public function generate(
        int $subscriptionId,
        array $data,
        bool $preview = false
    ): array {
        $atomic = (bool) ($data['atomic'] ?? false);
        $continueOnError = (bool) ($data['continue_on_error'] ?? true);
        $classes = array_values($data['classes'] ?? []);

        $this->ensureUniqueClassScopes($classes);

        $runner = function () use (
            $subscriptionId,
            $data,
            $classes,
            $preview,
            $atomic,
            $continueOnError
        ): array {
            $results = [];
            $successful = 0;
            $failed = 0;

            foreach ($classes as $index => $classData) {
                $payload = array_merge(
                    [
                        'academic_year_id' => $data['academic_year_id'] ?? null,
                        'timetable_template_id' => $data['timetable_template_id'],
                        'effective_from' => $data['effective_from'] ?? null,
                        'working_days' => $data['working_days'] ?? 6,
                        'allow_partial' => $data['allow_partial'] ?? false,
                    ],
                    $classData
                );

                try {
                    $result = $this->generator->generate(
                        $subscriptionId,
                        array_filter($payload, fn ($value) => $value !== null),
                        $preview
                    );

                    $successful++;
                    $results[] = [
                        'index' => $index,
                        'success' => true,
                        'class' => $this->classIdentity($classData),
                        'data' => $result,
                    ];
                } catch (Throwable $exception) {
                    $failed++;
                    $results[] = [
                        'index' => $index,
                        'success' => false,
                        'class' => $this->classIdentity($classData),
                        'message' => $exception->getMessage(),
                        'errors' => $exception instanceof ValidationException
                            ? $exception->errors()
                            : [],
                    ];

                    if ($atomic || ! $continueOnError) {
                        throw ValidationException::withMessages([
                            'classes.' . $index => $exception instanceof ValidationException
                                ? $exception->errors()
                                : [$exception->getMessage()],
                        ]);
                    }
                }
            }

            return [
                'preview' => $preview,
                'atomic' => $atomic,
                'requested_classes' => count($classes),
                'successful_classes' => $successful,
                'failed_classes' => $failed,
                'completion_percentage' => count($classes) > 0
                    ? round(($successful / count($classes)) * 100, 2)
                    : 100,
                'results' => $results,
            ];
        };

        if ($preview || ! $atomic) {
            return $runner();
        }

        return DB::transaction($runner);
    }

    private function ensureUniqueClassScopes(array $classes): void
    {
        $seen = [];

        foreach ($classes as $index => $classData) {
            $key = implode(':', [
                (int) ($classData['grade_id'] ?? 0),
                (int) ($classData['section_id'] ?? 0),
                (int) ($classData['stream_id'] ?? 0),
            ]);

            if (isset($seen[$key])) {
                throw ValidationException::withMessages([
                    'classes.' . $index => 'The same grade, section and stream scope appears more than once.',
                ]);
            }

            $seen[$key] = true;
        }
    }

    private function classIdentity(array $classData): array
    {
        return [
            'grade_id' => (int) $classData['grade_id'],
            'section_id' => isset($classData['section_id'])
                ? (int) $classData['section_id']
                : null,
            'stream_id' => isset($classData['stream_id'])
                ? (int) $classData['stream_id']
                : null,
            'name' => $classData['name'] ?? null,
            'weekly_timetable_id' => isset($classData['weekly_timetable_id'])
                ? (int) $classData['weekly_timetable_id']
                : null,
        ];
    }
}
