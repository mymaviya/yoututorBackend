<?php

namespace App\Services\AcademicPlanning\TeacherSubstitution;

use App\Models\TeacherSubstitution;
use App\Models\TimetableEntry;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TeacherSubstitutionService
{
    public function __construct(
        protected TeacherSubstitutionStatisticsService $statisticsService
    ) {}

    public function dashboard(
        int $subscriptionId,
        string $date,
        ?int $academicYearId = null
    ): array {
        $items = TeacherSubstitution::query()
            ->with($this->relations())
            ->where('subscription_id', $subscriptionId)
            ->when(
                $academicYearId,
                fn ($query) => $query->where('academic_year_id', $academicYearId)
            )
            ->whereDate('substitution_date', $date)
            ->latest('id')
            ->get()
            ->sortBy(fn (TeacherSubstitution $item) =>
                $item->timetableEntry?->bell?->sort_order ?? PHP_INT_MAX
            )
            ->values();

        $analytics = $this->statisticsService->dashboard(
            $subscriptionId,
            $academicYearId,
            $date
        );

        return [
            'stats' => $analytics['summary'] ?? [],
            'items' => $items,
            'analytics' => $analytics,
        ];
    }

    public function create(array $data): TeacherSubstitution
    {
        return DB::transaction(function () use ($data) {
            $entry = TimetableEntry::query()
                ->with(['weeklyTimetable.template', 'bell'])
                ->forSubscription((int) $data['subscription_id'])
                ->lockForUpdate()
                ->findOrFail((int) $data['timetable_entry_id']);

            if ((int) $entry->teacher_id !== (int) $data['original_teacher_id']) {
                throw ValidationException::withMessages([
                    'original_teacher_id' => 'The original teacher does not match the timetable entry.',
                ]);
            }

            $existing = TeacherSubstitution::query()
                ->where('subscription_id', (int) $data['subscription_id'])
                ->where('timetable_entry_id', $entry->id)
                ->whereDate('substitution_date', $data['substitution_date'])
                ->lockForUpdate()
                ->first();

            $substitution = $existing ?? new TeacherSubstitution();

            $substitution->fill([
                'subscription_id' => (int) $data['subscription_id'],
                'academic_year_id' => $data['academic_year_id'] ?? null,
                'teacher_availability_exception_id' => $data['teacher_availability_exception_id'] ?? null,
                'timetable_entry_id' => $entry->id,
                'original_teacher_id' => (int) $data['original_teacher_id'],
                'substitute_teacher_id' => (int) $data['substitute_teacher_id'],
                'grade_id' => $data['grade_id'] ?? null,
                'section_id' => $data['section_id'] ?? null,
                'subject_id' => $data['subject_id'] ?? $entry->subject_id,
                'substitution_date' => $data['substitution_date'],
                'reason' => $data['reason'] ?? null,
                'remarks' => $data['remarks'] ?? null,
                'status' => $data['status'] ?? TeacherSubstitution::STATUS_PENDING,
                'ai_score' => $data['ai_score'] ?? null,
                'ai_reason' => $data['ai_reason'] ?? null,
                'is_ai_suggested' => $data['is_ai_suggested'] ?? false,
                'ai_suggestions' => $data['ai_suggestions'] ?? null,
                'created_by' => (int) $data['created_by'],
            ]);

            $substitution->save();

            return $substitution->fresh($this->relations());
        });
    }

    public function assign(
        TeacherSubstitution $substitution,
        int $substituteTeacherId,
        int $assignedBy,
        ?float $aiScore = null,
        ?string $aiReason = null
    ): TeacherSubstitution {
        $substitution->update([
            'substitute_teacher_id' => $substituteTeacherId,
            'status' => TeacherSubstitution::STATUS_APPROVED,
            'created_by' => $assignedBy,
            'ai_score' => $aiScore,
            'ai_reason' => $aiReason,
            'is_ai_suggested' => $aiScore !== null,
        ]);

        return $substitution->fresh($this->relations());
    }

    public function approve(
        TeacherSubstitution $substitution,
        int $approvedBy
    ): TeacherSubstitution {
        $substitution->update([
            'status' => TeacherSubstitution::STATUS_COMPLETED,
            'created_by' => $approvedBy,
        ]);

        return $substitution->fresh($this->relations());
    }

    public function cancel(
        TeacherSubstitution $substitution,
        ?string $remarks = null
    ): TeacherSubstitution {
        $substitution->update([
            'status' => TeacherSubstitution::STATUS_REJECTED,
            'remarks' => $remarks,
        ]);

        return $substitution->fresh($this->relations());
    }

    public function pending(
        int $subscriptionId,
        ?string $date = null,
        ?int $academicYearId = null
    ): Collection {
        return TeacherSubstitution::query()
            ->with($this->relations())
            ->where('subscription_id', $subscriptionId)
            ->where('status', TeacherSubstitution::STATUS_PENDING)
            ->when(
                $academicYearId,
                fn ($query) => $query->where('academic_year_id', $academicYearId)
            )
            ->when(
                $date,
                fn ($query) => $query->whereDate('substitution_date', $date)
            )
            ->orderBy('substitution_date')
            ->latest('id')
            ->get();
    }

    private function relations(): array
    {
        return [
            'availabilityException',
            'originalTeacher',
            'absentTeacher',
            'substituteTeacher',
            'grade',
            'section',
            'subject',
            'timetableEntry.bell',
            'timetableEntry.weeklyTimetable',
            'createdBy',
        ];
    }
}
