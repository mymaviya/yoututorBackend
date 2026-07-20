<?php

namespace App\Services\AcademicPlanning;

use App\Models\TeacherAvailabilityException;
use App\Models\TeacherSubstitution;
use App\Models\TimetableEntry;
use App\Models\WeeklyTimetable;
use App\Services\AcademicPlanning\TeacherSubstitution\SubstituteSuggestionService;
use App\Services\AcademicPlanning\TeacherSubstitution\TeacherSubstitutionService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AutoSubstitutionGeneratorService
{
    public function __construct(
        protected SubstituteSuggestionService $suggestions,
        protected TeacherSubstitutionService $substitutions
    ) {}

    /**
     * Generate pending substitutions for every regular lesson affected by an
     * availability exception.
     */
    public function generateFromAvailabilityException(
        TeacherAvailabilityException $exception
    ): Collection {
        if (! $exception->blocksRegularTeaching()) {
            return collect();
        }

        $entries = $this->affectedEntries($exception);

        return DB::transaction(function () use ($exception, $entries): Collection {
            return $entries
                ->map(fn (TimetableEntry $entry) => $this->generateForEntry($exception, $entry))
                ->filter()
                ->values();
        });
    }

    /**
     * Rebuild pending automatic substitutions after an exception changes.
     */
    public function regenerate(TeacherAvailabilityException $exception): Collection
    {
        $this->cancel($exception, 'Automatically cancelled because the availability exception changed.');

        return $this->generateFromAvailabilityException($exception);
    }

    /**
     * Reject unresolved substitutions created for an exception.
     */
    public function cancel(
        TeacherAvailabilityException $exception,
        string $remarks = 'Automatically cancelled because the availability exception was removed.'
    ): int {
        return TeacherSubstitution::query()
            ->where('teacher_availability_exception_id', $exception->id)
            ->whereIn('status', [
                TeacherSubstitution::STATUS_PENDING,
                TeacherSubstitution::STATUS_APPROVED,
            ])
            ->update([
                'status' => TeacherSubstitution::STATUS_REJECTED,
                'remarks' => $remarks,
                'updated_at' => now(),
            ]);
    }

    protected function affectedEntries(
        TeacherAvailabilityException $exception
    ): Collection {
        $date = $exception->exception_date?->toDateString();

        if (! $date) {
            return collect();
        }

        $weekday = (int) ($exception->weekday ?: $exception->exception_date->isoWeekday());

        return TimetableEntry::query()
            ->with(['weeklyTimetable', 'bell'])
            ->active()
            ->where('teacher_id', (int) $exception->teacher_id)
            ->forWeekday($weekday)
            ->when(
                ! $exception->isFullDay(),
                fn ($query) => $query->where('school_bell_id', $exception->school_bell_id)
            )
            ->whereHas('weeklyTimetable', function ($query) use ($exception, $date): void {
                $query
                    ->where('subscription_id', (int) $exception->subscription_id)
                    ->where('status', WeeklyTimetable::STATUS_PUBLISHED)
                    ->where('is_active', true)
                    ->whereDate('effective_from', '<=', $date)
                    ->when(
                        $exception->academic_year_id,
                        fn ($yearQuery) => $yearQuery->where(
                            'academic_year_id',
                            (int) $exception->academic_year_id
                        )
                    );
            })
            ->get()
            ->unique(fn (TimetableEntry $entry) => $entry->id)
            ->values();
    }

    protected function generateForEntry(
        TeacherAvailabilityException $exception,
        TimetableEntry $entry
    ): ?TeacherSubstitution {
        $academicYearId = (int) (
            $exception->academic_year_id
            ?: $entry->weeklyTimetable?->academic_year_id
        );

        if (! $academicYearId || ! $entry->school_bell_id) {
            return null;
        }

        $date = $exception->exception_date->toDateString();

        $suggestions = $this->suggestions->suggest(
            (int) $exception->subscription_id,
            $academicYearId,
            (int) $exception->teacher_id,
            $date,
            (int) $entry->school_bell_id,
            $entry->subject_id ? (int) $entry->subject_id : null
        );

        $best = $suggestions->first();
        $substituteTeacherId = data_get($best, 'teacher.id');

        if (! $substituteTeacherId) {
            return null;
        }

        return $this->substitutions->create([
            'subscription_id' => (int) $exception->subscription_id,
            'academic_year_id' => $academicYearId,
            'teacher_availability_exception_id' => (int) $exception->id,
            'timetable_entry_id' => (int) $entry->id,
            'original_teacher_id' => (int) $exception->teacher_id,
            'substitute_teacher_id' => (int) $substituteTeacherId,
            'grade_id' => $entry->weeklyTimetable?->grade_id,
            'section_id' => $entry->weeklyTimetable?->section_id,
            'subject_id' => $entry->subject_id,
            'substitution_date' => $date,
            'reason' => $exception->displayReason()
                ?: 'Teacher availability exception',
            'status' => TeacherSubstitution::STATUS_PENDING,
            'ai_score' => data_get($best, 'score'),
            'ai_reason' => implode('; ', data_get($best, 'reasons', [])),
            'is_ai_suggested' => true,
            'ai_suggestions' => $suggestions->take(5)->values()->all(),
            'created_by' => (int) ($exception->created_by ?: 0),
        ]);
    }
}
