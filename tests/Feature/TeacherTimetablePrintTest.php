<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckActiveSubscription;
use App\Http\Middleware\CheckRouteFeature;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TeacherTimetablePrintTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware([
            CheckActiveSubscription::class,
            CheckRouteFeature::class,
        ]);
    }

    public function test_teacher_timetable_print_endpoint_returns_pdf_download(): void
    {
        $data = $this->createTimetableData();

        Sanctum::actingAs($data['user']);

        $response = $this->get('/api/teacher-timetable/print?' . http_build_query([
            'mode' => 'teacher',
            'teacher_id' => $data['teacher_id'],
            'academic_year_id' => $data['academic_year_id'],
        ]));

        $response
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->assertStringContainsString(
            'attachment;',
            (string) $response->headers->get('content-disposition')
        );

        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_class_timetable_print_endpoint_returns_pdf_download(): void
    {
        $data = $this->createTimetableData();

        Sanctum::actingAs($data['user']);

        $response = $this->get('/api/teacher-timetable/print?' . http_build_query([
            'mode' => 'class',
            'grade_id' => $data['grade_id'],
            'section_id' => $data['section_id'],
            'stream_id' => $data['stream_id'],
            'academic_year_id' => $data['academic_year_id'],
        ]));

        $response
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->assertStringContainsString(
            'attachment;',
            (string) $response->headers->get('content-disposition')
        );

        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    private function createTimetableData(): array
    {
        $now = now();

        $subscriptionId = DB::table('subscriptions')->insertGetId([
            'school_name' => 'YouTutor Print Test School',
            'status' => 'active',
            'amount' => 0,
            'starts_at' => now()->subDay()->toDateString(),
            'ends_at' => now()->addYear()->toDateString(),
            'is_trial' => false,
            'auto_renew' => false,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $userId = $this->insertUser($subscriptionId, 'Administrator', 'print-admin@example.test', 'admin');
        $teacherId = $this->insertUser($subscriptionId, 'Print Teacher', 'print-teacher@example.test', 'teacher');

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
            'name' => 'Grade 6',
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $streamId = DB::table('streams')->insertGetId([
            'name' => 'General',
            'code' => 'GEN',
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
            'room_no' => '301',
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $subjectId = DB::table('subjects')->insertGetId([
            'subscription_id' => $subscriptionId,
            'grade_id' => $gradeId,
            'stream_id' => $streamId,
            'name' => 'English',
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $bellId = DB::table('school_bells')->insertGetId([
            'title' => 'Period 1',
            'type' => 'period',
            'start_time' => '08:00:00',
            'duration_minutes' => 40,
            'end_time' => '08:40:00',
            'period_number' => 1,
            'is_teaching_period' => true,
            'is_break' => false,
            'is_dispersal' => false,
            'effective_from' => '2026-04-01',
            'is_active' => true,
            'sort_order' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

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
            'name' => 'Grade 6 A',
            'grade_id' => $gradeId,
            'section_id' => $sectionId,
            'stream_id' => $streamId,
            'effective_from' => '2026-04-01',
            'is_active' => true,
            'is_generated' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $entryId = DB::table('timetable_entries')->insertGetId([
            'weekly_timetable_id' => $weeklyTimetableId,
            'weekday' => 'Monday',
            'school_bell_id' => $bellId,
            'teacher_id' => $teacherId,
            'subject_id' => $subjectId,
            'lesson_id' => null,
            'parallel_group_id' => null,
            'student_group_name' => null,
            'room_no' => '301',
            'is_parallel' => false,
            'is_substitution' => false,
            'substitute_teacher_id' => null,
            'remarks' => null,
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
            'weekday' => 'Monday',
            'room_no' => '301',
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return [
            'user' => User::query()->findOrFail($userId),
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
}