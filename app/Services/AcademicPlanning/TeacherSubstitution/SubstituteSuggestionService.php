<?php

namespace App\Services\AcademicPlanning\TeacherSubstitution;

use App\Models\User;
use Illuminate\Support\Collection;

class SubstituteSuggestionService
{
    public function __construct(
        protected TeacherWorkloadService $workloadService,
        protected TeacherConflictService $conflictService
    ) {}

    public function suggest(
        int $subscriptionId,
        int $academicYearId,
        int $absentTeacherId,
        string $date,
        int $bellId,
        ?int $subjectId = null
    ): Collection {
        return User::teachers()
            ->where('subscription_id', $subscriptionId)
            ->where('is_active', true)
            ->where('id', '!=', $absentTeacherId)
            ->orderBy('name')
            ->get()
            ->map(function (User $teacher) use (
                $subscriptionId,
                $academicYearId,
                $date,
                $bellId,
                $subjectId
            ): array {
                $reasons = [];
                $warnings = [];

                $breakdown = [
                    'availability' => 0,
                    'subject' => 0,
                    'workload' => 0,
                    'history' => 0,
                    'preference' => 0,
                ];

                $conflicts = $this->conflictService->conflicts(
                    $subscriptionId,
                    $academicYearId,
                    (int) $teacher->id,
                    $date,
                    $bellId
                );

                $available = $conflicts === [];

                if ($available) {
                    $breakdown['availability'] = 25;
                    $reasons[] = 'Free this period';
                    $reasons[] = 'No timetable clash';
                } else {
                    $warnings = array_merge($warnings, $conflicts);
                }

                $subjectMatch = false;

                if ($subjectId !== null && method_exists($teacher, 'subjects')) {
                    $subjectMatch = $teacher->subjects()
                        ->where('subjects.id', $subjectId)
                        ->exists();
                }

                if ($subjectMatch) {
                    $breakdown['subject'] = 40;
                    $reasons[] = 'Same subject teacher';
                } elseif ($subjectId !== null) {
                    $breakdown['subject'] = 15;
                    $reasons[] = 'Available teacher from another subject';
                }

                $workload = $this->workloadService->summary(
                    $subscriptionId,
                    (int) $teacher->id,
                    $academicYearId,
                    $date
                );

                $breakdown['workload'] = (float) ($workload['workload_score'] ?? 0);

                if (($workload['today'] ?? 0) <= 4) {
                    $reasons[] = 'Low workload today';
                }

                if (($workload['today'] ?? 0) >= 7) {
                    $warnings[] = 'High workload today';
                }

                $monthlySubstitutions = (int) ($workload['monthly_substitutions'] ?? 0);

                if ($monthlySubstitutions === 0) {
                    $breakdown['history'] = 10;
                    $reasons[] = 'No substitutions this month';
                } elseif ($monthlySubstitutions <= 2) {
                    $breakdown['history'] = 7;
                    $reasons[] = 'Balanced substitution history';
                } elseif ($monthlySubstitutions <= 5) {
                    $breakdown['history'] = 4;
                    $warnings[] = 'Has handled some substitutions this month';
                } else {
                    $breakdown['history'] = 1;
                    $warnings[] = 'High monthly substitution count';
                }

                $score = $available ? array_sum($breakdown) : 0;
                $score = max(0, min(100, round($score, 2)));

                $recommendation = match (true) {
                    $score >= 90 => 'Excellent',
                    $score >= 75 => 'Recommended',
                    $score >= 60 => 'Fair',
                    default => 'Low',
                };

                return [
                    'teacher' => [
                        'id' => (int) $teacher->id,
                        'name' => $teacher->name,
                        'email' => $teacher->email,
                        'employee_code' => $teacher->employee_code ?? null,
                        'designation' => $teacher->designation ?? null,
                    ],
                    'score' => $score,
                    'recommendation' => $recommendation,
                    'breakdown' => $breakdown,
                    'subject_match' => $subjectMatch,
                    'availability' => [
                        'status' => $available ? 'available' : 'busy',
                        'free_this_period' => $available,
                    ],
                    'workload' => $workload,
                    'reasons' => array_values(array_unique($reasons)),
                    'warnings' => array_values(array_unique($warnings)),
                    'available' => $available,
                ];
            })
            ->filter(fn (array $row): bool => $row['available'])
            ->sortByDesc('score')
            ->values();
    }
}
