<?php

namespace Tests\Feature;;

use App\Models\User;
use App\Services\AcademicPlanning\TeacherTimetableService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class TeacherTimetableServiceTest extends TestCase
{
    use RefreshDatabase;

    private TeacherTimetableService $service;
    private array $data;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(TeacherTimetableService::class);
        $this->data = $this->createTimetableData();
    }

    public function test_teacher_timetable_returns_expected_data(): void
    {
        $result = $this->service->teacherTimetable(
            teacherId: $this->data['teacher_id'],
            academicYearId: $this->data['academic_year_id'],
            subscriptionId: $this->data['subscription_id'],
        );

        $this->assertIsArray($result);
        $this->assertSame($this->data['teacher_id'], $result['teacher']->id);
        $this->assertCount(3, $result['entries']);
        $this->assertArrayHasKey('bells', $result);
        $this->assertArrayHasKey('summary', $result);
    }

    public function test_class_timetable_returns_expected_data(): void
    {
        $result = $this->service->classTimetable(
            gradeId: $this->data['grade_id'],
            sectionId: $this->data['section_id'],
            streamId: $this->data['stream_id'],
            academicYearId: $this->data['academic_year_id'],
            subscriptionId: $this->data['subscription_id'],
        );

        $this->assertIsArray($result);
        $this->assertCount(3, $result['entries']);
        $this->assertSame($this->data['grade_id'], $result['entries']->first()->grade_id);
        $this->assertArrayHasKey('summary', $result);
    }

    public function test_workload_returns_summary(): void
    {
        $result = $this->service->workload(
            teacherId: $this->data['teacher_id'],
            academicYearId: $this->data['academic_year_id'],
            subscriptionId: $this->data['subscription_id'],
        );

        $this->assertSame($this->data['teacher_id'], $result['teacher_id']);
        $this->assertSame(3, $result['weekly_periods']);
        $this->assertSame(1, $result['subjects']);
        $this->assertSame(1, $result['substitutions']);
        $this->assertArrayHasKey('daily_load', $result);
    }

    public function test_free_periods_returns_all_weekdays(): void
    {
        $result = $this->service->freePeriods(
            teacherId: $this->data['teacher_id'],
            academicYearId: $this->data['academic_year_id'],
            subscriptionId: $this->data['subscription_id'],
        );

        $this->assertSame($this->data['teacher_id'], $result['teacher_id']);
        $this->assertIsArray($result['free_periods']);
        $this->assertSame(
            ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'],
            array_keys($result['free_periods'])
        );
    }

    public function test_today_returns_today_schedule(): void
    {
        $result = $this->service->today(
            teacherId: $this->data['teacher_id'],
            gradeId: $this->data['grade_id'],
            sectionId: $this->data['section_id'],
            streamId: $this->data['stream_id'],
            academicYearId: $this->data['academic_year_id'],
            subscriptionId: $this->data['subscription_id'],
        );

        $this->assertSame(now()->format('l'), $result['weekday']);
        $this->assertCount(1, $result['entries']);
        $this->assertArrayHasKey('bells', $result);
        $this->assertArrayHasKey('summary', $result);
    }

    public function test_summary_contains_required_keys(): void
    {
        $result = $this->service->teacherTimetable(
            teacherId: $this->data['teacher_id'],
            academicYearId: $this->data['academic_year_id'],
            subscriptionId: $this->data['subscription_id'],
        );

        $summary = $result['summary'];

        $this->assertSame(3, $summary['weekly_periods']);
        $this->assertSame(1, $summary['subjects']);
        $this->assertSame(1, $summary['substitutions']);
        $this->assertArrayHasKey('free_periods', $summary);
    }

    public function test_teacher_from_another_subscription_is_not_accessible(): void
    {
        $otherSubscriptionId = DB::table('subscriptions')->insertGetId([
            'school_name' => 'Other Test School',
            'status' => 'active',
            'amount' => 0,
            'starts_at' => now()->subDay()->toDateString(),
            'ends_at' => now()->addYear()->toDateString(),
            'is_trial' => false,
            'auto_renew' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

        $this->service->teacherTimetable(
            teacherId: $this->data['teacher_id'],
            academicYearId: $this->data['academic_year_id'],
            subscriptionId: $otherSubscriptionId,
        );
    }

    private function createTimetableData(): array
    {
        $now = now();

        $subscriptionId = DB::table('subscriptions')->insertGetId([
            'school_name' => 'YouTutor Service Test School',
            'status' => 'active',
            'amount' => 0,
            'starts_at' => now()->subDay()->toDateString(),
            'ends_at' => now()->addYear()->toDateString(),
            'is_trial' => false,
            'auto_renew' => false,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $teacherId = $this->insertUser(
            $subscriptionId,
            'Service Teacher',
            'service-teacher@example.test',
            'teacher'
        );

        $substituteTeacherId = $this->insertUser(
            $subscriptionId,
            'Substitute Teacher',
            'substitute-teacher@example.test',
            'teacher'
        );

        $academicYearId = DB::table('academic_years')->insertGetId([
            'subscription_id' => $subscriptionId,
            'name' => '2026-27',
            'start_date' => '2026-04-01',
            'end_date' => '2027-03-31',
            'is_current' => true,
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $gradeId = DB::table('grades')->insertGetId([
            'name' => 'Grade 9',
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $streamId = DB::table('streams')->insertGetId([
            'name' => 'Science',
            'code' => 'SCI',
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $sectionId = DB::table('sections')->insertGetId([
            'grade_id' => $gradeId,
            'stream_id' => $streamId,
            'name' => 'A',
            'display_name' => 'Section A',
            'capacity' => 40,
            'class_teacher_id' => $teacherId,
            'room_no' => '401',
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $subjectId = DB::table('subjects')->insertGetId([
            'subscription_id' => $subscriptionId,
            'grade_id' => $gradeId,
            'stream_id' => $streamId,
            'name' => 'Physics',
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $bellIds = [
            $this->insertBell('Period 1', 1, '08:00:00', '08:40:00', 1),
            $this->insertBell('Period 2', 2, '08:40:00', '09:20:00', 2),
            $this->insertBell('Period 3', 3, '09:20:00', '10:00:00', 3),
        ];

        $templateId = DB::table('timetable_templates')->insertGetId([
            'subscription_id' => $subscriptionId,
            'name' => 'Regular Timetable',
            'type' => 'regular',
            'effective_from' => '2026-04-01',
            'effective_to' => null,
            'is_default' => true,
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $weeklyTimetableId = DB::table('weekly_timetables')->insertGetId([
            'timetable_template_id' => $templateId,
            'academic_year_id' => $academicYearId,
            'name' => 'Grade 9 A',
            'grade_id' => $gradeId,
            'section_id' => $sectionId,
            'stream_id' => $streamId,
            'effective_from' => '2026-04-01',
            'is_active' => true,
            'is_generated' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $weekdays = [now()->format('l'), 'Monday', 'Tuesday'];

        if ($weekdays[0] === 'Monday') {
            $weekdays[1] = 'Wednesday';
        }

        if ($weekdays[0] === 'Tuesday') {
            $weekdays[2] = 'Thursday';
        }

        foreach ($bellIds as $index => $bellId) {
            $isSubstitution = $index === 2;

            $entryId = DB::table('timetable_entries')->insertGetId([
                'weekly_timetable_id' => $weeklyTimetableId,
                'weekday' => $weekdays[$index],
                'school_bell_id' => $bellId,
                'teacher_id' => $teacherId,
                'subject_id' => $subjectId,
                'lesson_id' => null,
                'parallel_group_id' => null,
                'student_group_name' => null,
                'room_no' => '401',
                'is_parallel' => false,
                'is_substitution' => $isSubstitution,
                'substitute_teacher_id' => $isSubstitution ? $substituteTeacherId : null,
                'remarks' => $isSubstitution ? 'Substitution test' : null,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('teacher_timetable_views')->insert([
                'timetable_entry_id' => $entryId,
                'teacher_id' => $teacherId,
                'grade_id' => $gradeId,
                'section_id' => $sectionId,
                'stream_id' => $streamId,
                'subject_id' => $subjectId,
                'school_bell_id' => $bellId,
                'weekday' => $weekdays[$index],
                'room_no' => '401',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        return [
            'subscription_id' => $subscriptionId,
            'teacher' => User::query()->findOrFail($teacherId),
            'teacher_id' => $teacherId,
            'academic_year_id' => $academicYearId,
            'grade_id' => $gradeId,
            'section_id' => $sectionId,
            'stream_id' => $streamId,
        ];
    }

    private function insertUser(int $subscriptionId, string $name, string $email, string $role): int
    {
        return DB::table('users')->insertGetId([
            'role_id' => null,
            'subscription_id' => $subscriptionId,
            'name' => $name,
            'email' => $email,
            'password' => Hash::make('password'),
            'role' => $role,
            'is_active' => true,
            'login_enabled' => true,
            'session_timeout_minutes' => 30,
            'allow_multiple_sessions' => false,
            'password_change_required' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function insertBell(
        string $title,
        int $periodNumber,
        string $startTime,
        string $endTime,
        int $sortOrder
    ): int {
        return DB::table('school_bells')->insertGetId([
            'title' => $title,
            'type' => 'period',
            'start_time' => $startTime,
            'duration_minutes' => 40,
            'end_time' => $endTime,
            'period_number' => $periodNumber,
            'is_teaching_period' => true,
            'is_break' => false,
            'is_dispersal' => false,
            'effective_from' => '2026-04-01',
            'is_active' => true,
            'sort_order' => $sortOrder,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}