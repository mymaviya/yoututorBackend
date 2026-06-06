<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Route as RouteFacade;

use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\DashboardController;
use App\Http\Controllers\API\GradeController;
use App\Http\Controllers\API\SubjectController;
use App\Http\Controllers\API\LessonController;
use App\Http\Controllers\API\QuestionController;
use App\Http\Controllers\API\QuestionPaperController;
use App\Http\Controllers\API\QuestionPaperPdfController;
use App\Http\Controllers\API\TeacherController;
use App\Http\Controllers\API\TeacherReportController;
use App\Http\Controllers\API\TeacherQuestionTaskController;
use App\Http\Controllers\API\TeacherDashboardController;
use App\Http\Controllers\API\QuestionApprovalController;
use App\Http\Controllers\API\ProfileController;
use App\Http\Controllers\API\StudentController;
use App\Http\Controllers\API\NotificationController;
use App\Http\Controllers\API\ExamPortionController;
use App\Http\Controllers\API\ExamNameController;
use App\Http\Controllers\API\PaperBlueprintController;
use App\Http\Controllers\API\AutoPaperGeneratorController;
use App\Http\Controllers\API\QuestionTypeController;
use App\Http\Controllers\API\PaperGeneratorController;
use App\Http\Controllers\API\LanguageQuestionController;
use App\Http\Controllers\API\TeacherAnalyticsController;
use App\Http\Controllers\API\RoleController;
use App\Http\Controllers\API\PermissionController;
use App\Http\Controllers\API\UserController;
use App\Http\Controllers\API\LoginHolidayController;
use App\Http\Controllers\API\UserDeviceController;
use App\Http\Controllers\API\UserSecurityController;
use App\Http\Controllers\API\AuditLogController;
use App\Http\Controllers\API\SidebarMenuController;
use App\Models\Permission;
use App\Models\SidebarMenu;


Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::middleware('auth:sanctum')->post('/change-first-password', [AuthController::class, 'changeFirstPassword']);

Route::middleware('auth:sanctum')->group(function () {

    Route::post('/logout', [AuthController::class, 'logout']);

    Route::post('/heartbeat', function (Request $request) {
        $request->user()->update([
            'last_activity_at' => now(),
        ]);
    });

    Route::get('/current-user', function (Request $request) {
        $user = $request->user();

        $user->loadMissing('roleData.permissions');

        $roleSlug = $user->roleData?->slug ?? $user->role;

        $permissionSlugs = $roleSlug === 'admin'
            ? Permission::pluck('slug')
            : ($user->roleData?->permissions->pluck('slug') ?? collect());

        $sidebarMenus = SidebarMenu::where('is_active', true)
            ->where('show_in_sidebar', true)
            ->orderBy('sort_order')
            ->get()
            ->filter(function ($menu) use ($permissionSlugs) {
                if (!$menu->permission_slug) {
                    return true;
                }

                return $permissionSlugs->contains($menu->permission_slug);
            })
            ->values();

        $roleSlug = $user->roleData?->slug ?? $user->role;

        $dashboardRoute = match ($roleSlug) {
            'teacher' => 'teacher.dashboard',
            default => 'Dashboard',
        };

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'contact' => $user->contact,
            'address' => $user->address,
            'role' => $roleSlug,
            'role_id' => $user->role_id ?? null,
            'login_enabled' => $user->login_enabled,
            'daily_login_start_time' => $user->daily_login_start_time,
            'daily_login_end_time' => $user->daily_login_end_time,
            'login_start_date' => $user->login_start_date,
            'login_end_date' => $user->login_end_date,
            'profile' => $user->profile ? asset('storage/' . $user->profile) : null,
            'permissions' => $permissionSlugs->values(),
            'sidebar_menus' => $sidebarMenus,
            'dashboard_route' => $dashboardRoute,
            'password_change_required' => (bool) $user->password_change_required,
        ]);
    });

    Route::post('/profile/update', [ProfileController::class, 'update']);
    Route::post('/profile/change-password', [ProfileController::class, 'changePassword']);

    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount']);
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markAsRead']);
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead']);
    Route::post('/notifications/mark-group-read', [NotificationController::class, 'markGroupRead']);

    Route::middleware('role:admin,teacher')->group(function () {
        Route::get('/grades', [GradeController::class, 'index']);
        Route::get('/subjects', [SubjectController::class, 'index']);
        Route::get('/lessons', [LessonController::class, 'index']);
        Route::get('/question-types', [QuestionTypeController::class, 'index']);

        Route::apiResource('questions', QuestionController::class);
        Route::apiResource('question-papers', QuestionPaperController::class);

        Route::post('/papers/auto-generate', [QuestionPaperController::class, 'autoGenerate']);
        Route::post('/papers/generate-from-blueprint', [AutoPaperGeneratorController::class, 'generate']);
        Route::get('/question-papers/{id}/pdf', [QuestionPaperPdfController::class, 'download']);

        Route::post('/paper-generator/preview', [PaperGeneratorController::class, 'preview']);
        Route::post('/paper-generator/generate', [PaperGeneratorController::class, 'generate']);

        Route::get('/teacher/my-question-tasks', [TeacherQuestionTaskController::class, 'myTasks']);

        Route::get('/my-exam-portions', [ExamPortionController::class, 'myPortions']);
        Route::post('/exam-portions/{examPortion}/submit', [ExamPortionController::class, 'submit']);

        Route::get('/my-assignments', function () {
            $user = auth()->user();

            if ($user->role === 'admin') {
                return response()->json([
                    'is_admin' => true,
                    'grades' => [],
                    'subjects' => [],
                ]);
            }

            $teacher = $user->teacher;

            if (!$teacher) {
                return response()->json([
                    'is_admin' => false,
                    'grades' => [],
                    'subjects' => [],
                ]);
            }

            $assignments = $teacher->assignments()
                ->with(['grade', 'subject'])
                ->get();

            return response()->json([
                'is_admin' => false,

                'grades' => $assignments
                    ->pluck('grade')
                    ->filter()
                    ->unique('id')
                    ->values(),

                'subjects' => $assignments
                    ->filter(fn($a) => $a->subject)
                    ->map(fn($a) => [
                        'id' => $a->subject->id,
                        'name' => $a->subject->name,
                        'grade_id' => $a->grade_id,
                    ])
                    ->values(),
            ]);
        });
    });

    Route::middleware('role:admin')->group(function () {

        Route::get('/app-routes', function () {
            return collect(RouteFacade::getRoutes())
                ->map(function ($route) {
                    return [
                        'uri' => $route->uri(),
                        'name' => $route->getName(),
                        'methods' => $route->methods(),
                        'action' => $route->getActionName(),
                    ];
                })
                ->filter(fn($route) => $route['name'])
                ->values();
        });

        Route::get('/dashboard', [DashboardController::class, 'index']);

        Route::apiResource('grades', GradeController::class)->except(['index']);
        Route::apiResource('subjects', SubjectController::class)->except(['index']);
        Route::apiResource('lessons', LessonController::class)->except(['index']);

        Route::post('/grade_status/{id}', [GradeController::class, 'status']);
        Route::post('/subject_status/{id}', [SubjectController::class, 'status']);

        Route::apiResource('teacher-question-tasks', TeacherQuestionTaskController::class);

        Route::get('/reports/teacher-question-paper-progress', [TeacherReportController::class, 'questionPaperProgress']);

        Route::get('/question-approvals', [QuestionApprovalController::class, 'index']);
        Route::post('/question-approvals/{question}/reject', [QuestionApprovalController::class, 'reject']);
        Route::post('/question-approvals/{question}/approve', [QuestionApprovalController::class, 'approve']);

        Route::apiResource('exam-portions', ExamPortionController::class);
        Route::post('/exam-portions/{examPortion}/approve', [ExamPortionController::class, 'approve']);
        Route::post('/exam-portions/{examPortion}/reject', [ExamPortionController::class, 'reject']);

        Route::apiResource('exam-names', ExamNameController::class);
        Route::post('/exam-names/{examName}/status', [ExamNameController::class, 'status']);

        Route::get('/paper-blueprints/dropdown', [PaperBlueprintController::class, 'dropdown']);
        Route::post('/paper-blueprints/{id}/status', [PaperBlueprintController::class, 'status']);
        Route::post('/paper-blueprints/{id}/copy', [PaperBlueprintController::class, 'copy']);
        Route::apiResource('paper-blueprints', PaperBlueprintController::class);

        Route::post('/question-types/{questionType}/status', [QuestionTypeController::class, 'status']);
        Route::post('/question-types/import', [QuestionTypeController::class, 'import']);
        Route::get('/question-types/template', [QuestionTypeController::class, 'downloadTemplate']);

        Route::apiResource('question-types', QuestionTypeController::class)->except(['index']);

        Route::get('/language-questions/group', [LanguageQuestionController::class, 'group']);
        Route::get('/teacher-analytics', [TeacherAnalyticsController::class, 'index']);

        // Roles & Permission Management
        Route::apiResource('roles', RoleController::class);
        Route::apiResource('permissions', PermissionController::class);
        Route::get('/users', [UserController::class, 'index']);

        Route::get('/roles/{role}/permissions', [RoleController::class, 'permissions']);
        Route::post('/roles/{role}/permissions', [RoleController::class, 'syncPermissions']);

        Route::get('/users/{user}/permissions', [UserController::class, 'permissions']);
        Route::post('/users/{user}/permissions', [UserController::class, 'syncPermissions']);
        Route::post('/users/bulk-login-access', [UserController::class, 'bulkLoginAccess']);
        Route::apiResource('users', UserController::class);

        Route::apiResource('login-holidays', LoginHolidayController::class);

        Route::get('/user-devices', [UserDeviceController::class, 'index']);
        Route::post('/user-devices/{device}/trust', [UserDeviceController::class, 'trust']);
        Route::post('/user-devices/{device}/block', [UserDeviceController::class, 'block']);
        Route::delete('/user-devices/{device}', [UserDeviceController::class, 'destroy']);

        Route::get('/users/{user}/security', [UserSecurityController::class, 'show']);
        Route::put('/users/{user}/security', [UserSecurityController::class, 'update']);

        Route::get('/audit-logs', [AuditLogController::class, 'index']);

        Route::post('/teachers/import-preview', [TeacherController::class, 'importPreview']);
        Route::get('/teachers/importTemplate', [TeacherController::class, 'downloadTemplate']);
        Route::post('/teachers/import', [TeacherController::class, 'import']);
        Route::apiResource('teachers', TeacherController::class);

        Route::post('/sidebar-menus/reorder', [SidebarMenuController::class, 'reorder']);
        Route::apiResource('sidebar-menus', SidebarMenuController::class);

        Route::post('question-papers/{id}/finalize', [QuestionPaperController::class, 'finalize']);
        Route::post('question-papers/{id}/reopen', [QuestionPaperController::class, 'reopen']);
        Route::post('question-papers/{id}/printed', [QuestionPaperController::class, 'markPrinted']);
        Route::post('question-papers/{id}/archive', [QuestionPaperController::class, 'archive']);
    });



    Route::middleware('role:teacher')->group(function () {
        Route::get('/teacher/dashboard', [TeacherDashboardController::class, 'index']);
        Route::get('/students', [StudentController::class, 'index']);
    });

    Route::middleware('role:student')->group(function () {
        Route::get('/profile', function (Request $request) {
            return $request->user();
        });
    });
});
