<?php

namespace App\Services\AcademicPlanning\TeacherSubstitution;

use App\Models\TeacherSubstitution;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TeacherSubstitutionService
{
    public function __construct(
        protected TeacherSubstitutionStatisticsService $statisticsService
    ) {}

    public function dashboard(
        ?int $subscriptionId,
        string $date,
        ?int $academicYearId = null
    ): array {
        $items = TeacherSubstitution::with([
            'absentTeacher',
            'substituteTeacher',
            'grade',
            'section',
            'subject',
            'bell',
        ])
            ->where('subscription_id', $subscriptionId)
            ->when($subscriptionId, fn ($query) => $query->where('subscription_id', $subscriptionId))
            ->when($academicYearId, fn($query) => $query->where('academic_year_id', $academicYearId))
            ->whereDate('substitution_date', $date)
            ->orderBy('school_bell_id')
            ->latest()
            ->get();

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
            return TeacherSubstitution::updateOrCreate(
                [
                    'subscription_id' => $data['subscription_id'],
                    'academic_year_id' => $data['academic_year_id'] ?? null,
                    'teacher_availability_exception_id' => $data['teacher_availability_exception_id'] ?? null,
                    'absent_teacher_id' => $data['absent_teacher_id'],
                    'school_bell_id' => $data['school_bell_id'],
                    'substitution_date' => $data['substitution_date'],
                ],
                [
                    'timetable_entry_id' => $data['timetable_entry_id'] ?? null,
                    'substitute_teacher_id' => $data['substitute_teacher_id'] ?? null,
                    'grade_id' => $data['grade_id'] ?? null,
                    'section_id' => $data['section_id'] ?? null,
                    'subject_id' => $data['subject_id'] ?? null,
                    'reason' => $data['reason'] ?? null,
                    'remarks' => $data['remarks'] ?? null,
                    'status' => $data['status'] ?? 'pending',
                    'assigned_by' => $data['assigned_by'] ?? null,
                    'is_active' => $data['is_active'] ?? true,
                ]
            )->fresh($this->relations());
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
            'status' => 'assigned',
            'assigned_by' => $assignedBy,
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
            'status' => 'completed',
            'approved_by' => $approvedBy,
            'approved_at' => now(),
        ]);

        return $substitution->fresh($this->relations());
    }

    public function cancel(
        TeacherSubstitution $substitution,
        ?string $remarks = null
    ): TeacherSubstitution {
        $substitution->update([
            'status' => 'cancelled',
            'remarks' => $remarks,
        ]);

        return $substitution->fresh($this->relations());
    }

    public function pending(
        int $subscriptionId,
        ?string $date = null,
        ?int $academicYearId = null
    ): Collection {
        return TeacherSubstitution::with($this->relations())
            ->where('subscription_id', $subscriptionId)
            ->where('status', 'pending')
            ->when($academicYearId, fn($query) => $query->where('academic_year_id', $academicYearId))
            ->when($date, fn($query) => $query->whereDate('substitution_date', $date))
            ->orderBy('substitution_date')
            ->orderBy('school_bell_id')
            ->get();
    }

    private function relations(): array
    {
        return [
            'absentTeacher',
            'substituteTeacher',
            'grade',
            'section',
            'subject',
            'bell',
        ];
    }
}
