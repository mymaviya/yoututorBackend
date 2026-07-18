<?php

namespace App\Services\AcademicPlanning\TeacherSubstitution;

use App\Events\TeacherSubstitutionAutoAssigned;
use App\Models\TeacherAvailabilityException;
use App\Models\TeacherSubstitution;
use App\Models\TimetableEntry;
use Illuminate\Support\Facades\DB;

class AutoSubstitutionGeneratorService
{
    private const STATUS_PENDING = 'pending';
    private const STATUS_APPROVED = 'approved';
    private const STATUS_REJECTED = 'rejected';
    private const STATUS_COMPLETED = 'completed';

    public function __construct(
        protected SubstituteSuggestionService $suggestionService
    ) {}

    public function generateFromAvailabilityException(
        TeacherAvailabilityException $exception
    ): void {
        if (! $this->shouldGenerate($exception)) {
            return;
        }

        DB::transaction(function () use ($exception) {
            $entries = $this->affectedTimetableEntries($exception);

            foreach ($entries as $entry) {
                $existing = TeacherSubstitution::query()
                    ->where('teacher_availability_exception_id', $exception->id)
                    ->where('timetable_entry_id', $entry->id)
                    ->whereDate('substitution_date', $exception->exception_date)
                    ->first();

                if (
                    $existing &&
                    in_array($existing->status, [
                        self::STATUS_APPROVED,
                        self::STATUS_COMPLETED,
                    ], true)
                ) {
                    continue;
                }

                $suggestions = $this->suggestionService->suggest(
                    (int) $exception->subscription_id,
                    (int) $exception->academic_year_id,
                    (int) $exception->teacher_id,
                    $exception->exception_date->toDateString(),
                    (int) $entry->school_bell_id,
                    $entry->subject_id
                );

                $bestSuggestion = $suggestions->first();

                if (! $bestSuggestion || empty($bestSuggestion['teacher']['id'])) {
                    continue;
                }

                $autoAssignable = (float) $bestSuggestion['score'] >= 90
                    && empty($bestSuggestion['warnings']);

                $substitution = TeacherSubstitution::updateOrCreate(
                    [
                        'subscription_id' => $exception->subscription_id,
                        'academic_year_id' => $exception->academic_year_id,
                        'teacher_availability_exception_id' => $exception->id,
                        'timetable_entry_id' => $entry->id,
                        'original_teacher_id' => $exception->teacher_id,
                        'substitution_date' => $exception->exception_date,
                    ],
                    [
                        'substitute_teacher_id' => $bestSuggestion['teacher']['id'],
                        'grade_id' => $entry->grade_id ?? null,
                        'section_id' => $entry->section_id ?? null,
                        'subject_id' => $entry->subject_id ?? null,
                        'reason' => $exception->reason ?: 'Teacher on leave',
                        'remarks' => $exception->remarks,
                        'status' => $autoAssignable ? self::STATUS_APPROVED : self::STATUS_PENDING,
                        'created_by' => $exception->created_by ?? auth()->id(),
                        'ai_score' => $bestSuggestion['score'] ?? null,
                        'ai_reason' => implode(', ', $bestSuggestion['reasons'] ?? []),
                        'is_ai_suggested' => true,
                        'ai_suggestions' => $suggestions->take(5)->values()->toArray(),
                    ]
                );

                $wasAutoAssigned =
                    $substitution->wasRecentlyCreated ||
                    $substitution->wasChanged('substitute_teacher_id') ||
                    $substitution->wasChanged('status');

                if ($autoAssignable && $wasAutoAssigned) {
                    event(new TeacherSubstitutionAutoAssigned($substitution));

                    logger()->info('Teacher substitution auto assigned.', [
                        'substitution_id' => $substitution->id,
                        'original_teacher_id' => $exception->teacher_id,
                        'substitute_teacher_id' => $substitution->substitute_teacher_id,
                        'score' => $bestSuggestion['score'] ?? null,
                    ]);
                }
            }
        });
    }

    public function regenerate(
        TeacherAvailabilityException $exception
    ): void {
        DB::transaction(function () use ($exception) {
            $this->cancelPending(
                $exception,
                'Automatically refreshed because the availability exception changed.'
            );

            if ($this->shouldGenerate($exception)) {
                $this->generateFromAvailabilityException($exception->fresh());
            }
        });
    }

    public function cancel(
        TeacherAvailabilityException $exception,
        string $remarks = 'Automatically rejected because the availability exception was removed.'
    ): void {
        $this->cancelPending($exception, $remarks);
    }

    private function shouldGenerate(
        TeacherAvailabilityException $exception
    ): bool {
        return
            (bool) $exception->is_active &&
            $exception->status === TeacherAvailabilityException::STATUS_LEAVE &&
            ! empty($exception->exception_date) &&
            ! empty($exception->academic_year_id) &&
            ! empty($exception->teacher_id) &&
            ! empty($exception->weekday);
    }

    private function cancelPending(
        TeacherAvailabilityException $exception,
        string $remarks
    ): void {
        TeacherSubstitution::query()
            ->where('teacher_availability_exception_id', $exception->id)
            ->where('status', self::STATUS_PENDING)
            ->update([
                'status' => self::STATUS_REJECTED,
                'remarks' => $remarks,
            ]);
    }

    private function affectedTimetableEntries(
        TeacherAvailabilityException $exception
    ) {
        return TimetableEntry::query()
            ->where('teacher_id', $exception->teacher_id)
            ->whereHas('weeklyTimetable', function ($query) use ($exception) {
                $query->where('academic_year_id', $exception->academic_year_id);
            })
            ->where('weekday', $exception->weekday)
            ->when(
                ! $exception->is_full_day,
                fn ($query) => $query->where('school_bell_id', $exception->school_bell_id)
            )
            ->get();
    }
}
