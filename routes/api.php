<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Route as RouteFacade;

use App\Http\Middleware\CheckActiveSubscription;
use App\Http\Middleware\CheckRouteFeature;

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\GradeController;
use App\Http\Controllers\Api\SubjectController;
use App\Http\Controllers\Api\LessonController;
use App\Http\Controllers\Api\QuestionController;
use App\Http\Controllers\Api\QuestionPaperController;
use App\Http\Controllers\Api\QuestionPaperPdfController;
use App\Http\Controllers\Api\TeacherController;
use App\Http\Controllers\Api\TeacherReportController;
use App\Http\Controllers\Api\TeacherQuestionTaskController;
use App\Http\Controllers\Api\TeacherDashboardController;
use App\Http\Controllers\Api\QuestionApprovalController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\StudentController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\ExamPortionController;
use App\Http\Controllers\Api\ExamNameController;
use App\Http\Controllers\Api\PaperBlueprintController;
use App\Http\Controllers\Api\AutoPaperGeneratorController;
use App\Http\Controllers\Api\QuestionTypeController;
use App\Http\Controllers\Api\PaperGeneratorController;
use App\Http\Controllers\Api\LanguageQuestionController;
use App\Http\Controllers\Api\TeacherAnalyticsController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\PermissionController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\LoginHolidayController;
use App\Http\Controllers\Api\UserDeviceController;
use App\Http\Controllers\Api\UserSecurityController;
use App\Http\Controllers\Api\AuditLogController;
use App\Http\Controllers\Api\SidebarMenuController;
use App\Http\Controllers\Api\SubjectTemplateController;
use App\Http\Controllers\Api\QuestionTypeTemplateController;
use App\Http\Controllers\Api\BlueprintImportController;

use App\Http\Controllers\Api\Public\SubscriptionPlanController;
use App\Http\Controllers\Api\Public\DemoEnquiryController;
use App\Http\Controllers\Api\Public\RazorpayPaymentController;
use App\Http\Controllers\Api\Public\PublicSettingController;
use App\Http\Controllers\Api\Admin\SubscriptionRenewalController;

use App\Http\Controllers\Api\Admin\DemoEnquiryController as AdminDemoEnquiryController;
use App\Http\Controllers\Api\Admin\SubscriptionController;
use App\Http\Controllers\Api\Admin\SubscriptionPlanController as AdminSubscriptionPlanController;
use App\Http\Controllers\Api\Admin\SettingController;
use App\Http\Controllers\Api\Admin\PaymentTransactionController;
use App\Http\Controllers\Api\Admin\LicenseKeyController;
use App\Http\Controllers\Api\Public\RazorpayWebhookController;

use App\Models\SidebarMenu;
use App\Models\Stream;

/*
|--------------------------------------------------------------------------
| Public Auth Routes
|--------------------------------------------------------------------------
*/

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

/*
|--------------------------------------------------------------------------
| Public Website Routes
|--------------------------------------------------------------------------
| These routes must remain open for website, pricing, demo enquiry,
| settings, checkout and Razorpay payment flow.
*/

Route::prefix('public')->group(function () {
    Route::get('/plans', [SubscriptionPlanController::class, 'index']);
    Route::post('/demo-enquiries', [DemoEnquiryController::class, 'store']);
    Route::get('/settings', [PublicSettingController::class, 'index']);
});

Route::prefix('public/payment')->group(function () {
    Route::post('/create-order', [RazorpayPaymentController::class, 'createOrder']);
    Route::post('/verify', [RazorpayPaymentController::class, 'verifyPayment']);
});

Route::post('/webhooks/razorpay', [RazorpayWebhookController::class, 'handle']);
/*
|--------------------------------------------------------------------------
| Authenticated Common Routes
|--------------------------------------------------------------------------
| These routes must work even if subscription is expired, because users
| need logout, current user, password change and basic profile access.
*/

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::post('/change-first-password', [AuthController::class, 'changeFirstPassword']);

    Route::post('/heartbeat', function (Request $request) {
        $request->user()->update([
            'last_activity_at' => now(),
        ]);

        return response()->json([
            'success' => true,
        ]);
    });

    Route::get('/current-user', function (Request $request) {
        $user = $request->user();

        $user->loadMissing('roleData.permissions');

        $roleSlug = $user->roleData?->slug ?? $user->role;

        $permissionSlugs = $user->roleData?->permissions->pluck('slug') ?? collect();

        $subscription = \App\Models\Subscription::with('plan.featureItems')
            ->whereIn('status', ['active', 'trial'])
            ->whereDate('ends_at', '>=', now())
            ->latest()
            ->first();

        $allowedFeatureKeys = $subscription?->plan?->featureItems
            ?->where('is_enabled', true)
            ->pluck('feature_key')
            ?? collect();

        $sidebarMenus = SidebarMenu::where('is_active', true)
            ->where('show_in_sidebar', true)
            ->orderBy('sort_order')
            ->get()
            ->filter(function ($menu) use ($permissionSlugs, $roleSlug, $allowedFeatureKeys) {
                if (!empty($menu->role_slug) && $menu->role_slug !== $roleSlug) {
                    return false;
                }

                if (!empty($menu->feature_key) && !$allowedFeatureKeys->contains($menu->feature_key)) {
                    return false;
                }

                if (empty($menu->permission_slug)) {
                    return true;
                }

                return $permissionSlugs->contains($menu->permission_slug);
            })
            ->values();

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
});

/*
|--------------------------------------------------------------------------
| SaaS Admin Management Routes
|--------------------------------------------------------------------------
| These are not protected by CheckActiveSubscription so super admin can
| renew, activate, extend or manage subscriptions even after expiry.
*/

Route::middleware(['auth:sanctum', 'role:admin, superadmin'])
    ->prefix('admin')
    ->group(function () {
        Route::apiResource('subscription-plans', AdminSubscriptionPlanController::class);

        Route::get('/subscriptions/dashboard', [SubscriptionController::class, 'dashboard']);
        Route::get('/subscription-status', [SubscriptionController::class, 'currentStatus']);

        Route::apiResource('subscriptions', SubscriptionController::class);

        Route::put('/subscriptions/{subscription}/activate-paid-plan', [SubscriptionController::class, 'activate']);
        Route::put('/subscriptions/{subscription}/extend', [SubscriptionController::class, 'extend']);
        Route::put('/subscriptions/{subscription}/status', [SubscriptionController::class, 'updateStatus']);
        Route::post('/subscriptions/{subscription}/suspend', [SubscriptionController::class, 'suspend']);
        Route::post('/subscriptions/{subscription}/cancel', [SubscriptionController::class, 'cancel']);

        Route::get('/subscription-renewals', [SubscriptionRenewalController::class, 'index']);
        Route::post('/subscriptions/{subscription}/renew', [SubscriptionRenewalController::class, 'renew']);

        Route::get('/payment-transactions', [PaymentTransactionController::class, 'index']);
        Route::get('/payment-transactions/{paymentTransaction}', [PaymentTransactionController::class, 'show']);

        Route::get('/license-keys', [LicenseKeyController::class, 'index']);
        Route::get('/license-keys/{licenseKey}', [LicenseKeyController::class, 'show']);
        Route::put('/license-keys/{licenseKey}/status', [LicenseKeyController::class, 'updateStatus']);
        Route::put('/license-keys/{licenseKey}/extend', [LicenseKeyController::class, 'extend']);
        Route::post('/license-keys/{licenseKey}/regenerate', [LicenseKeyController::class, 'regenerate']);

        Route::get('/demo-enquiries', [AdminDemoEnquiryController::class, 'index']);
        Route::get('/demo-enquiries/{demoEnquiry}', [AdminDemoEnquiryController::class, 'show']);
        Route::put('/demo-enquiries/{demoEnquiry}/status', [AdminDemoEnquiryController::class, 'updateStatus']);
        Route::put('/demo-enquiries/{demoEnquiry}/start-demo', [AdminDemoEnquiryController::class, 'startDemo']);
        Route::post('/demo-enquiries/{demoEnquiry}/remarks', [AdminDemoEnquiryController::class, 'addRemark']);
        Route::put('/demo-enquiries/{demoEnquiry}/follow-up', [AdminDemoEnquiryController::class, 'updateFollowUp']);
        Route::post('/demo-enquiries/{demoEnquiry}/convert', [AdminDemoEnquiryController::class, 'convertToSubscription']);

        Route::get('/settings', [SettingController::class, 'index']);
        Route::put('/settings', [SettingController::class, 'update']);
    });

/*
|--------------------------------------------------------------------------
| ERP Protected Routes
|--------------------------------------------------------------------------
| These routes require valid Sanctum login and active SaaS subscription.
*/

Route::middleware([
    'auth:sanctum',
    CheckActiveSubscription::class,
    CheckRouteFeature::class,
])->group(function () {

    Route::get('/streams', function () {
        return Stream::where('is_active', true)
            ->orderBy('name')
            ->get();
    });

    /*
    |--------------------------------------------------------------------------
    | Admin / Teacher Common Academic Routes
    |--------------------------------------------------------------------------
    */

    Route::middleware('role:admin, superadmin,teacher')->group(function () {
        Route::get('/grades', [GradeController::class, 'index'])->name('grades.index');
        Route::get('/subjects', [SubjectController::class, 'index'])->name('subjects.index');
        Route::get('/lessons', [LessonController::class, 'index'])->name('lessons.index');
        Route::get('/question-types', [QuestionTypeController::class, 'index'])->name('question.types');

        Route::apiResource('questions', QuestionController::class)->names([
            'index' => 'questions.index',
            'store' => 'questions.create',
            'show' => 'questions.show',
            'update' => 'questions.edit',
            'destroy' => 'questions.delete',
        ]);
        Route::apiResource('question-papers', QuestionPaperController::class)->names([
            'index' => 'papers.index',
            'store' => 'papers.create',
            'show' => 'question.paper.view',
            'update' => 'paper.edit',
            'destroy' => 'papers.delete',
        ]);

        Route::post('/papers/auto-generate', [QuestionPaperController::class, 'autoGenerate']);
        Route::post('/papers/generate-from-blueprint', [AutoPaperGeneratorController::class, 'generateFromBlueprint']);
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
                    ->filter(fn($assignment) => $assignment->subject)
                    ->map(fn($assignment) => [
                        'id' => $assignment->subject->id,
                        'name' => $assignment->subject->name,
                        'grade_id' => $assignment->grade_id,
                    ])
                    ->values(),
            ]);
        });
    });

    /*
    |--------------------------------------------------------------------------
    | Admin Academic / ERP Management Routes
    |--------------------------------------------------------------------------
    */

    Route::middleware('role:admin, superadmin')->group(function () {
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

        Route::post('/questions/import', [QuestionController::class, 'import']);
        Route::get('/questions/import-template', [QuestionController::class, 'downloadTemplate']);

        Route::post('/lessons/import', [LessonController::class, 'import']);
        Route::get('/lessons/import-template', [LessonController::class, 'downloadTemplate']);

        Route::apiResource('grades', GradeController::class)->except(['index']);
        Route::post('/grade_status/{id}', [GradeController::class, 'status']);

        Route::apiResource('subjects', SubjectController::class)->except(['index']);
        Route::post('/subjects/{id}/status', [SubjectController::class, 'status']);

        Route::apiResource('lessons', LessonController::class)->except(['index']);

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

        Route::post('/question-papers/{id}/finalize', [QuestionPaperController::class, 'finalize']);
        Route::post('/question-papers/{id}/reopen', [QuestionPaperController::class, 'reopen']);
        Route::post('/question-papers/{id}/printed', [QuestionPaperController::class, 'markPrinted']);
        Route::post('/question-papers/{id}/archive', [QuestionPaperController::class, 'archive']);

        Route::apiResource('subject-templates', SubjectTemplateController::class);
        Route::post('/subject-templates/{subjectTemplate}/apply', [SubjectTemplateController::class, 'apply']);

        Route::get('/question-type-template-masters', [QuestionTypeTemplateController::class, 'masters']);
        Route::apiResource('question-type-templates', QuestionTypeTemplateController::class);
        Route::post('/question-type-templates/{questionTypeTemplate}/apply', [QuestionTypeTemplateController::class, 'apply']);

        Route::post('/blueprint-import/question-type-template', [BlueprintImportController::class, 'importQuestionTypeTemplate']);
        Route::post('/blueprint-import/paper-blueprint', [BlueprintImportController::class, 'importPaperBlueprint']);
        Route::post('/blueprint-import/all', [BlueprintImportController::class, 'importAll']);
        Route::get('/blueprint-import/template', [BlueprintImportController::class, 'downloadTemplate']);
    });

    /*
    |--------------------------------------------------------------------------
    | Teacher Routes
    |--------------------------------------------------------------------------
    */

    Route::middleware('role:teacher')->group(function () {
        Route::get('/teacher/dashboard', [TeacherDashboardController::class, 'index']);
        Route::get('/students', [StudentController::class, 'index']);
    });

    /*
    |--------------------------------------------------------------------------
    | Student Routes
    |--------------------------------------------------------------------------
    */

    Route::middleware('role:student')->group(function () {
        Route::get('/profile', function (Request $request) {
            return $request->user();
        });
    });
});
