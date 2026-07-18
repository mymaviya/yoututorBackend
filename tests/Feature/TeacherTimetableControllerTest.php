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

class TeacherTimetableControllerTest extends TestCase
{
    use RefreshDatabase;

    private array $data;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware([
            CheckActiveSubscription::class,
            CheckRouteFeature::class,
        ]);

        $this->data = $this->createTimetableData();
    }

    public function test_teacher_timetable_index_returns_success(): void
    {
        Sanctum::actingAs($this->data['user']);

        $response = $this->getJson('/api/teacher-timetable?' . http_build_query([
            'mode' => 'teacher',
            'teacher_id' => $this->data['teacher']->id,
            'academic_year_id' => $this->data['academic_year_id'],
        ]));

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.teacher.id', $this->data['teacher']->id)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'teacher',
                    'bells',
                    'entries',
                    'summary' => [
                        'weekly_periods',
                        'free_periods',
                        'substitutions',
                        'subjects',
                    ],
                ],
            ]);
    }

    public function test_teacher_timetable_teacher_endpoint_returns_success(): void
    {
        Sanctum::actingAs($this->data['user']);

        $response = $this->getJson(
            "/api/teacher-timetable/teacher/{$this->data['teacher']->id}?" . http_build_query([
                'academic_year_id' => $this->data['academic_year_id'],
            ])
        );

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.teacher.id', $this->data['teacher']->id)
            ->assertJsonCount(2, 'data.entries');
    }

    public function test_teacher_timetable_class_endpoint_returns_success(): void
    {
        Sanctum::actingAs($this->data['user']);

        $response = $this->getJson('/api/teacher-timetable/class?' . http_build_query([
            'grade_id' => $this->data['grade_id'],
            'section_id' => $this->data['section_id'],
            'stream_id' => $this->data['stream_id'],
            'academic_year_id' => $this->data['academic_year_id'],
        ]));

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'data.entries');
    }

    public function test_today_endpoint_returns_current_weekday_schedule(): void
    {
        Sanctum::actingAs($this->data['user']);

        $response = $this->getJson('/api/teacher-timetable/today?' . http_build_query([
            'teacher_id' => $this->data['teacher']->id,
            'academic_year_id' => $this->data['academic_year_id'],
        ]));

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.weekday', now()->format('l'))
            ->assertJsonStructure([
                'success',
                'data' => [
                    'weekday',
                    'bells',
                    'entries',
                    'summary',
                ],
            ]);
    }

    public function test_workload_endpoint_returns_summary(): void
    {
        Sanctum::actingAs($this->data['user']);

        $response = $this->getJson('/api/teacher-timetable/workload?' . http_build_query([
            'teacher_id' => $this->data['teacher']->id,
            'academic_year_id' => $this->data['academic_year_id'],
        ]));

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.teacher_id', $this->data['teacher']->id)
            ->assertJsonPath('data.weekly_periods', 2)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'teacher_id',
                    'weekly_periods',
                    'daily_load',
                    'subjects',
                    'substitutions',
                ],
            ]);
    }

    public function test_free_periods_endpoint_returns_weekday_groups(): void
    {
        Sanctum::actingAs($this->data['user']);

        $response = $this->getJson('/api/teacher-timetable/free-periods?' . http_build_query([
            'teacher_id' => $this->data['teacher']->id,
            'academic_year_id' => $this->data['academic_year_id'],
        ]));

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.teacher_id', $this->data['teacher']->id)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'teacher_id',
                    'free_periods' => [
                        'Monday',
                        'Tuesday',
                        'Wednesday',
                        'Thursday',
                        'Friday',
                        'Saturday',
                    ],
                ],
            ]);
    }

    public function test_export_endpoint_returns_excel_download(): void
    {
        Sanctum::actingAs($this->data['user']);

        $response = $this->get('/api/teacher-timetable/export?' . http_build_query([
            'mode' => 'teacher',
            'teacher_id' => $this->data['teacher']->id,
            'academic_year_id' => $this->data['academic_year_id'],
        ]));

        $response
            ->assertOk()
            ->assertHeader(
                'content-type',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
            );

        $this->assertStringContainsString(
            'attachment;',
            (string) $response->headers->get('content-disposition')
        );
    }

    public function test_print_endpoint_returns_pdf_download(): void
    {
        Sanctum::actingAs($this->data['user']);

        $response = $this->get('/api/teacher-timetable/print?' . http_build_query([
            'mode' => 'class',
            'grade_id' => $this->data['grade_id'],
            'section_id' => $this->data['section_id'],
            'stream_id' => $this->data['stream_id'],
            'academic_year_id' => $this->data['academic_year_id'],
        ]));

        $response
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->assertStringContainsString(
            'attachment;',
            (string) $response->headers->get('content-disposition')
        );
    }

    public function test_index_requires_teacher_id_in_teacher_mode(): void
    {
        Sanctum::actingAs($this->data['user']);

        $this->getJson('/api/teacher-timetable?mode=teacher')
            ->assertStatus(422);
    }

    public function test_class_endpoint_requires_grade_id(): void
    {
        Sanctum::actingAs($this->data['user']);

        $this->getJson('/api/teacher-timetable/class')
            ->assertStatus(422)
            ->assertJsonValidationErrors('grade_id');
    }

    public function test_guest_cannot_access_routes(): void
    {
        $this->getJson('/api/teacher-timetable?' . http_build_query([
            'mode' => 'teacher',
            'teacher_id' => $this->data['teacher']->id,
        ]))->assertUnauthorized();
    }

    private function createTimetableData(): array
    {
        $now = now();
        $subscriptionId = DB::table('subscriptions')->insertGetId([
            'school_name' => 'YouTutor Test School',
            'status' => 'active',
            'amount' => 0,
            'starts_at' => now()->subDay()->toDateString(),
            'ends_at' => now()->addYear()->toDateString(),
            'is_trial' => false,
            'auto_renew' => false,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $userId = $this->insertUser($subscriptionId, 'Administrator', 'admin@example.test', 'admin');
        $teacherId = $this->insertUser($subscriptionId, 'Teacher One', 'teacher@example.test', 'teacher');

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
            'name' => 'Grade 8',
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
            'room_no' => '101',
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $subjectId = DB::table('subjects')->insertGetId([
            'subscription_id' => $subscriptionId,
            'grade_id' => $gradeId,
            'stream_id' => $streamId,
            'name' => 'Mathematics',
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $bellIds = [
            $this->insertBell('Period 1', 1, '08:00:00', '08:40:00', 1),
            $this->insertBell('Period 2', 2, '08:40:00', '09:20:00', 2),
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
            'name' => 'Grade 8 A Regular',
            'grade_id' => $gradeId,
            'section_id' => $sectionId,
            'stream_id' => $streamId,
            'effective_from' => '2026-04-01',
            'is_active' => true,
            'is_generated' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $weekdays = [now()->format('l'), now()->format('l') === 'Monday' ? 'Tuesday' : 'Monday'];

        foreach ($bellIds as $index => $bellId) {
            $entryId = DB::table('timetable_entries')->insertGetId([
                'weekly_timetable_id' => $weeklyTimetableId,
                'weekday' => $weekdays[$index],
                'school_bell_id' => $bellId,
                'teacher_id' => $teacherId,
                'subject_id' => $subjectId,
                'lesson_id' => null,
                'parallel_group_id' => null,
                'student_group_name' => null,
                'room_no' => '101',
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
                'weekday' => $weekdays[$index],
                'room_no' => '101',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        return [
            'subscription_id' => $subscriptionId,
            'user' => User::query()->findOrFail($userId),
            'teacher' => User::query()->findOrFail($teacherId),
            'academic_year_id' => $academicYearId,
            'grade_id' => $gradeId,
            'section_id' => $sectionId,
            'stream_id' => $streamId,
            'subject_id' => $subjectId,
            'weekly_timetable_id' => $weeklyTimetableId,
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