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
use App\Http\Controllers\Api\Public\RazorpayWebhookController;
use App\Http\Controllers\Api\Public\DesktopDashboardController;

use App\Http\Controllers\Api\Admin\DemoEnquiryController as AdminDemoEnquiryController;
use App\Http\Controllers\Api\Admin\SubscriptionController;
use App\Http\Controllers\Api\Admin\SubscriptionPlanController as AdminSubscriptionPlanController;
use App\Http\Controllers\Api\Admin\SettingController;
use App\Http\Controllers\Api\Admin\PaymentTransactionController;
use App\Http\Controllers\Api\Admin\LicenseKeyController;
use App\Http\Controllers\Api\Admin\SubscriptionRenewalController;

use App\Http\Controllers\Api\ProposalTemplateController;
use App\Http\Controllers\Api\ProposalController;
use App\Http\Controllers\Api\ServiceCatalogController;
use App\Http\Controllers\Api\QuotationController;
use App\Http\Controllers\Api\InvoiceController;
use App\Http\Controllers\Api\CrmDashboardController;

use App\Http\Controllers\Api\Admin\QuestionBankPackageController;
use App\Http\Controllers\Api\Admin\MasterQuestionController;
use App\Http\Controllers\Api\Admin\SubscriptionQuestionBankPurchaseController;
use App\Http\Controllers\Api\Admin\MasterQuestionBankController;

use App\Http\Controllers\Api\AiPaperGeneratorController;

use App\Http\Controllers\Api\TeacherProfileController;
use App\Http\Controllers\Api\SchoolProfileController;
use App\Http\Controllers\Api\BellScheduleController;
use App\Http\Controllers\Api\SchoolNoticeController;

use App\Http\Controllers\Api\Admin\AcademicPlanningController;
use App\Http\Controllers\Api\Admin\SubjectPeriodAllocationController;

use App\Http\Controllers\Api\SectionController;
use App\Http\Controllers\Api\AcademicYearController;
use App\Http\Controllers\Api\TeacherAvailabilityController;
use App\Http\Controllers\Api\TeacherAvailabilityExceptionController;
use App\Http\Controllers\Api\TeacherSubstitutionController;
use App\Http\Controllers\Api\TeacherTimetableController;
use App\Http\Controllers\Api\TeacherTimetableExportController;
use App\Http\Controllers\Api\TeacherTimetablePrintController;


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

Route::get('/desktop-dashboard/data', [DesktopDashboardController::class, 'data']);

/*
|--------------------------------------------------------------------------
| Authenticated Common Routes
|--------------------------------------------------------------------------
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

        $user->loadMissing([
            'roleData.permissions',
            'subscription.plan.featureItems',
        ]);

        $roleSlug = $user->roleData?->slug ?? $user->role;

        $permissionSlugs = $user->roleData?->permissions->pluck('slug') ?? collect();

        $isSuperAdmin = in_array($roleSlug, ['superadmin', 'super_admin'], true);

        $subscription = $user->subscription;

        $allowedFeatureKeys = $isSuperAdmin
            ? SidebarMenu::whereNotNull('feature_key')->pluck('feature_key')
            : (
                $subscription?->plan?->featureItems
                ?->where('is_enabled', true)
                ->pluck('feature_key')
                ?? collect()
            );

        $sidebarMenus = SidebarMenu::where('is_active', true)
            ->where('show_in_sidebar', true)
            ->orderBy('sort_order')
            ->get()
            ->filter(function ($menu) use ($permissionSlugs, $roleSlug, $allowedFeatureKeys, $isSuperAdmin) {
                if ($isSuperAdmin) {
                    return true;
                }

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
            'subscription_id' => $user->subscription_id,
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
            'allowed_feature_keys' => $allowedFeatureKeys->values(),
        ]);
    });

    Route::post('/profile/update', [ProfileController::class, 'update']);
    Route::post('/profile/change-password', [ProfileController::class, 'changePassword']);

    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount']);
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markAsRead']);
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead']);
    Route::post('/notifications/mark-group-read', [NotificationController::class, 'markGroupRead']);

    Route::get('/my-subscription-status', [SubscriptionController::class, 'currentStatus']);
});

/*
|--------------------------------------------------------------------------
| SaaS Super Admin Management Routes
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:sanctum', 'role:superadmin,super_admin'])
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
        Route::put('/subscriptions/{subscription}/school-profile', [SubscriptionController::class, 'updateSchoolProfile']);

        Route::get('/subscription-renewals', [SubscriptionRenewalController::class, 'index']);
        Route::post('/subscriptions/{subscription}/renew', [SubscriptionRenewalController::class, 'renew']);

        Route::get('/payment-transactions', [PaymentTransactionController::class, 'index']);
        Route::get('/payment-transactions/{paymentTransaction}', [PaymentTransactionController::class, 'show']);

        Route::get('/license-keys', [LicenseKeyController::class, 'index']);
        Route::get('/license-keys/{licenseKey}', [LicenseKeyController::class, 'show']);
        Route::put('/license-keys/{licenseKey}/status', [LicenseKeyController::class, 'updateStatus']);
        Route::put('/license-keys/{licenseKey}/extend', [LicenseKeyController::class, 'extend']);
        Route::post('/license-keys/{licenseKey}/regenerate', [LicenseKeyController::class, 'regenerate']);

        Route::get('/crm/dashboard', [CrmDashboardController::class, 'index']);

        Route::get('/demo-enquiries', [AdminDemoEnquiryController::class, 'index']);
        Route::get('/demo-enquiries/{demoEnquiry}', [AdminDemoEnquiryController::class, 'show']);
        Route::put('/demo-enquiries/{demoEnquiry}/status', [AdminDemoEnquiryController::class, 'updateStatus']);
        Route::put('/demo-enquiries/{demoEnquiry}/start-demo', [AdminDemoEnquiryController::class, 'startDemo']);
        Route::post('/demo-enquiries/{demoEnquiry}/remarks', [AdminDemoEnquiryController::class, 'addRemark']);
        Route::put('/demo-enquiries/{demoEnquiry}/follow-up', [AdminDemoEnquiryController::class, 'updateFollowUp']);
        Route::post('/demo-enquiries/{demoEnquiry}/convert', [AdminDemoEnquiryController::class, 'convertToSubscription']);

        Route::get('/settings', [SettingController::class, 'index']);
        Route::put('/settings', [SettingController::class, 'update']);

        Route::get('/proposal-templates', [ProposalTemplateController::class, 'index']);
        Route::get('/proposal-templates/{id}', [ProposalTemplateController::class, 'show']);
        Route::post('/proposal-templates', [ProposalTemplateController::class, 'store']);
        Route::put('/proposal-templates/{id}', [ProposalTemplateController::class, 'update']);
        Route::delete('/proposal-templates/{id}', [ProposalTemplateController::class, 'destroy']);

        Route::apiResource('proposals', ProposalController::class);
        Route::put('/proposals/{id}/sections', [ProposalController::class, 'updateSections']);
        Route::put('/proposals/{id}/items', [ProposalController::class, 'updateItems']);
        Route::get('/proposals/{id}/versions', [ProposalController::class, 'versions']);
        Route::put('/proposals/{id}/send', [ProposalController::class, 'send']);
        Route::put('/proposals/{id}/approve', [ProposalController::class, 'approve']);
        Route::put('/proposals/{id}/reject', [ProposalController::class, 'reject']);
        Route::put('/proposals/{id}/request-changes', [ProposalController::class, 'requestChanges']);
        Route::post('/proposals/{id}/convert-to-quotation', [ProposalController::class, 'convertToQuotation']);
        Route::get('/proposals/{id}/pdf', [ProposalController::class, 'generatePdf']);

        Route::get('/service-catalogs', [ServiceCatalogController::class, 'index']);
        Route::get('/service-catalogs/{id}', [ServiceCatalogController::class, 'show']);

        Route::get('/quotations', [QuotationController::class, 'index']);
        Route::get('/quotations/{id}', [QuotationController::class, 'show']);
        Route::put('/quotations/{id}', [QuotationController::class, 'update']);
        Route::put('/quotations/{id}/send', [QuotationController::class, 'send']);
        Route::put('/quotations/{id}/accept', [QuotationController::class, 'accept']);
        Route::put('/quotations/{id}/reject', [QuotationController::class, 'reject']);
        Route::post('/quotations/{id}/convert-to-invoice', [QuotationController::class, 'convertToInvoice']);
        Route::get('/quotations/{id}/pdf', [QuotationController::class, 'generatePdf']);

        Route::get('/invoices', [InvoiceController::class, 'index']);
        Route::get('/invoices/{id}', [InvoiceController::class, 'show']);
        Route::put('/invoices/{id}', [InvoiceController::class, 'update']);
        Route::put('/invoices/{id}/send', [InvoiceController::class, 'send']);
        Route::put('/invoices/{id}/mark-paid', [InvoiceController::class, 'markPaid']);
        Route::put('/invoices/{id}/cancel', [InvoiceController::class, 'cancel']);
        Route::get('/invoices/{id}/pdf', [InvoiceController::class, 'generatePdf']);

        Route::post('/master-questions/import-to-school', [MasterQuestionController::class, 'importToSchool'])->name('master-questions.import-to-school');
        Route::get('/master-questions/import-template', [MasterQuestionController::class, 'downloadTemplate'])->name('admin.master-questions.import-template');
        Route::post('/master-questions/import', [MasterQuestionController::class, 'import'])->name('admin.master-questions.import');
        Route::apiResource('question-bank-packages', QuestionBankPackageController::class);
        Route::apiResource('question-bank-purchases', SubscriptionQuestionBankPurchaseController::class)->parameters(['question-bank-purchases' => 'questionBankPurchase',]);
        Route::apiResource('master-questions', MasterQuestionController::class);
    });

/*
|--------------------------------------------------------------------------
| ERP Protected Routes
|--------------------------------------------------------------------------
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
    })->name('streams.index');

    Route::middleware('role:admin,superadmin,super_admin,teacher')->group(function () {
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

        Route::post('/papers/auto-generate', [QuestionPaperController::class, 'autoGenerate'])
            ->name('papers.auto-generate');

        Route::post('/papers/generate-from-blueprint', [AutoPaperGeneratorController::class, 'generateFromBlueprint'])
            ->name('papers.generate-from-blueprint');

        Route::get('/question-papers/{id}/pdf', [QuestionPaperPdfController::class, 'exportPdf'])
            ->name('question-papers.pdf');

        Route::get('/question-papers/{id}/export-pdf', [QuestionPaperPdfController::class, 'exportPdf'])
            ->name('question-papers.export-pdf');

        Route::get('/question-papers/{id}/answer-key-pdf', [QuestionPaperPdfController::class, 'answerKeyPdf'])
            ->name('question-papers.answer-key-pdf');

        Route::get('/question-papers/{id}/export-word', [QuestionPaperController::class, 'exportWord'])
            ->name('question-papers.export-word');

        Route::get('/question-papers/{id}/answer-key-word', [QuestionPaperController::class, 'exportAnswerKeyWord'])
            ->name('question-papers.answer-key-word');

        Route::post('/paper-generator/preview', [PaperGeneratorController::class, 'preview'])
            ->name('paper.generator.preview');

        Route::post('/paper-generator/generate', [PaperGeneratorController::class, 'generate'])
            ->name('paper.generator.generate');

        Route::get('/teacher/my-question-tasks', [TeacherQuestionTaskController::class, 'myTasks'])
            ->name('teacher.my.tasks');

        Route::get('/my-exam-portions', [ExamPortionController::class, 'myPortions'])
            ->name('teacher.exam.portions');

        Route::get('/exam-portions/by-exam/{examNameId}', [ExamPortionController::class, 'byExam']);

        Route::post('/exam-portions/{examPortion}/submit', [ExamPortionController::class, 'submit'])
            ->name('exam-portions.submit');

        Route::get('/master-question-bank/packages', [MasterQuestionBankController::class, 'packages'])
            ->name('master-question-bank.packages');

        Route::get('/master-question-bank/questions', [MasterQuestionBankController::class, 'questions'])
            ->name('master-question-bank.questions');

        Route::post('/master-question-bank/import', [MasterQuestionBankController::class, 'import'])
            ->name('master-question-bank.import');

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
                'grades' => $assignments->pluck('grade')->filter()->unique('id')->values(),
                'subjects' => $assignments
                    ->filter(fn($assignment) => $assignment->subject)
                    ->map(fn($assignment) => [
                        'id' => $assignment->subject->id,
                        'name' => $assignment->subject->name,
                        'grade_id' => $assignment->grade_id,
                    ])
                    ->values(),
            ]);
        })->name('teacher.assignments');
    });

    Route::middleware('role:admin,superadmin,super_admin')->group(function () {

        Route::get('/school-profile', [SchoolProfileController::class, 'show'])
            ->name('school.profile');

        Route::post('/school-profile', [SchoolProfileController::class, 'update'])
            ->name('school.profile.update');

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
        })->name('app.routes');

        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

        Route::get('/questions/import-template', [QuestionController::class, 'downloadTemplate'])
            ->name('question.import.template');

        Route::post('/questions/import', [QuestionController::class, 'import'])
            ->name('question.import');

        Route::post('/lessons/import', [LessonController::class, 'import'])
            ->name('lesson.import');

        Route::get('/lessons/import-template', [LessonController::class, 'downloadTemplate'])
            ->name('lesson.import.template');

        Route::apiResource('grades', GradeController::class)->except(['index']);
        Route::post('/grade_status/{id}', [GradeController::class, 'status'])->name('grades.status');

        Route::apiResource('subjects', SubjectController::class)->except(['index']);
        Route::post('/subjects/{id}/status', [SubjectController::class, 'status'])->name('subjects.status');

        Route::apiResource('lessons', LessonController::class)->except(['index']);

        Route::apiResource('teacher-question-tasks', TeacherQuestionTaskController::class)->names([
            'index' => 'teacher.tasks',
        ]);

        Route::get('/reports/teacher-question-paper-progress', [TeacherReportController::class, 'questionPaperProgress'])
            ->name('teacher.progress');

        Route::get('/question-approvals', [QuestionApprovalController::class, 'index'])
            ->name('question.approvals');

        Route::post('/question-approvals/{question}/reject', [QuestionApprovalController::class, 'reject'])
            ->name('question.approvals.reject');

        Route::post('/question-approvals/{question}/approve', [QuestionApprovalController::class, 'approve'])
            ->name('question.approvals.approve');

        Route::apiResource('exam-portions', ExamPortionController::class)->names([
            'index' => 'exam.portions',
        ]);

        Route::post('/exam-portions/{examPortion}/approve', [ExamPortionController::class, 'approve'])
            ->name('exam-portions.approve');

        Route::post('/exam-portions/{examPortion}/reject', [ExamPortionController::class, 'reject'])
            ->name('exam-portions.reject');

        Route::apiResource('exam-names', ExamNameController::class)->names([
            'index' => 'exam.names',
        ]);

        Route::post('/exam-names/{examName}/status', [ExamNameController::class, 'status'])
            ->name('exam-names.status');

        Route::get('/paper-blueprints/dropdown', [PaperBlueprintController::class, 'dropdown'])
            ->name('paper.blueprints.dropdown');

        Route::post('/paper-blueprints/{id}/status', [PaperBlueprintController::class, 'status'])
            ->name('paper.blueprints.status');

        Route::post('/paper-blueprints/{id}/copy', [PaperBlueprintController::class, 'copy'])
            ->name('paper.blueprints.copy');

        Route::get('/paper-blueprints/list', [PaperBlueprintController::class, 'list',]);

        Route::apiResource('paper-blueprints', PaperBlueprintController::class)->names([
            'index' => 'paper.blueprints',
        ]);

        Route::post('/question-types/{questionType}/status', [QuestionTypeController::class, 'status'])
            ->name('question-types.status');

        Route::post('/question-types/import', [QuestionTypeController::class, 'import'])
            ->name('question-types.import');

        Route::get('/question-types/template', [QuestionTypeController::class, 'downloadTemplate'])
            ->name('question-type-templates');

        Route::apiResource('question-types', QuestionTypeController::class)->except(['index']);

        Route::get('/language-questions/group', [LanguageQuestionController::class, 'group'])
            ->name('language.questions.group');

        Route::get('/teacher-analytics', [TeacherAnalyticsController::class, 'index'])
            ->name('teacher.analytics');

        Route::apiResource('roles', RoleController::class);
        Route::apiResource('permissions', PermissionController::class);

        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::get('/roles/{role}/permissions', [RoleController::class, 'permissions'])->name('roles.permissions');
        Route::post('/roles/{role}/permissions', [RoleController::class, 'syncPermissions'])->name('roles.permissions.sync');
        Route::get('/users/{user}/permissions', [UserController::class, 'permissions'])->name('users.permissions');
        Route::post('/users/{user}/permissions', [UserController::class, 'syncPermissions'])->name('users.permissions.sync');
        Route::post('/users/bulk-login-access', [UserController::class, 'bulkLoginAccess'])->name('users.bulk-login-access');
        Route::apiResource('users', UserController::class);

        Route::apiResource('login-holidays', LoginHolidayController::class);

        Route::get('/user-devices', [UserDeviceController::class, 'index'])->name('user-devices.index');
        Route::post('/user-devices/{device}/trust', [UserDeviceController::class, 'trust'])->name('user-devices.trust');
        Route::post('/user-devices/{device}/block', [UserDeviceController::class, 'block'])->name('user-devices.block');
        Route::delete('/user-devices/{device}', [UserDeviceController::class, 'destroy'])->name('user-devices.destroy');

        Route::get('/users/{user}/security', [UserSecurityController::class, 'show'])->name('users.security.show');
        Route::put('/users/{user}/security', [UserSecurityController::class, 'update'])->name('users.security.update');

        Route::get('/audit-logs', [AuditLogController::class, 'index'])->name('audit.logs');

        Route::post('/teachers/import-preview', [TeacherController::class, 'importPreview'])->name('teachers.import.preview');
        Route::get('/teachers/importTemplate', [TeacherController::class, 'downloadTemplate'])->name('teachers.import.template');
        Route::post('/teachers/import', [TeacherController::class, 'import'])->name('teachers.import');
        Route::apiResource('teachers', TeacherController::class)->names([
            'index' => 'teachers.index',
        ]);

        Route::get('/bell-schedules', [BellScheduleController::class, 'index'])
            ->name('bell-schedules.index');

        Route::get('/bell-schedules/settings', [BellScheduleController::class, 'settings'])
            ->name('bell-schedules.settings');

        Route::post('/bell-schedules/settings', [BellScheduleController::class, 'saveSettings'])
            ->name('bell-schedules.settings.save');

        Route::post('/bell-schedules/generate', [BellScheduleController::class, 'generate'])
            ->name('bell-schedules.generate');

        Route::get('/bell-schedules/preview', [BellScheduleController::class, 'preview'])
            ->name('bell-schedules.preview');

        Route::apiResource('school-notices', SchoolNoticeController::class);

        Route::post('school-notices/{schoolNotice}/toggle', [SchoolNoticeController::class, 'toggle']);

        Route::prefix('academic-planning')
            ->controller(AcademicPlanningController::class)
            ->group(function () {

                Route::get('/dashboard', 'dashboard')
                    ->name('academic.planning.dashboard');

                Route::get('/statistics', 'statistics')
                    ->name('academic.planning.statistics');

                Route::get('/readiness', 'readiness')
                    ->name('academic.planning.readiness');

                Route::get('/warnings', 'warnings')
                    ->name('academic.planning.warnings');
            });

        Route::post('/subject-period-allocations/bulk-save', [SubjectPeriodAllocationController::class, 'bulkSave'])
            ->name('subject.allocation.bulk-save');
        Route::get('/subject-period-allocations/bulk-editor-data', [SubjectPeriodAllocationController::class, 'bulkEditorData'])
            ->name('subject.allocation.bulk-editor-data');
        Route::post('/subject-period-allocations/copy-grade', [SubjectPeriodAllocationController::class, 'copyGrade'])
            ->name('subject.allocation.copy-grade');
        Route::get('/subject-period-allocations/export', [SubjectPeriodAllocationController::class, 'export'])
            ->name('subject.allocation.export');
        Route::get('/subject-period-allocations/template', [SubjectPeriodAllocationController::class, 'template'])
            ->name('subject.allocation.template');

        Route::apiResource('subject-period-allocations', SubjectPeriodAllocationController::class);

        Route::prefix('teacher-availability')->group(function () {

            Route::get('/bulk-editor-data', [TeacherAvailabilityController::class, 'bulkEditorData'])->name('teacher.availability.bulk-editor-data');

            Route::apiResource('/', TeacherAvailabilityController::class);
        });

        Route::prefix('teacher-timetable')
            ->name('teacher.timetable.')
            ->group(function () {
                /*
         * Static routes are declared before parameterised routes so future
         * route additions cannot accidentally be captured as a teacher ID.
         */
                Route::get('/', [TeacherTimetableController::class, 'index'])
                    ->name('index');

                Route::get('/class', [TeacherTimetableController::class, 'classTimetable'])
                    ->name('class');

                Route::get('/today', [TeacherTimetableController::class, 'today'])
                    ->name('today');

                Route::get('/free-periods', [TeacherTimetableController::class, 'freePeriods'])
                    ->name('free-periods');

                Route::get('/workload', [TeacherTimetableController::class, 'workload'])
                    ->name('workload');

                Route::get('/export', [TeacherTimetableExportController::class, 'export'])
                    ->name('export');

                Route::get('/print', [TeacherTimetablePrintController::class, 'print'])
                    ->name('print');

                Route::get('/teacher/{teacher}', [TeacherTimetableController::class, 'teacher'])
                    ->whereNumber('teacher')
                    ->name('teacher');
            });

        Route::prefix('teacher-availability-exceptions')->group(function () {
            Route::get('/dashboard', [TeacherAvailabilityExceptionController::class, 'dashboard'])
                ->name('teacher.availability.dashboard');

            Route::get('/', [TeacherAvailabilityExceptionController::class, 'index'])
                ->name('teacher.availability');

            Route::post('/', [TeacherAvailabilityExceptionController::class, 'store'])
                ->name('teacher.availability.store');

            Route::patch('/{teacherAvailabilityException}/move', [TeacherAvailabilityExceptionController::class, 'move'])
                ->name('teacher.availability.move');

            Route::put('/{teacherAvailabilityException}', [TeacherAvailabilityExceptionController::class, 'update'])
                ->name('teacher.availability.update');

            Route::delete('/{teacherAvailabilityException}', [TeacherAvailabilityExceptionController::class, 'destroy'])
                ->name('teacher.availability.delete');
        });

        Route::prefix('teacher-substitutions')->group(function () {
            Route::get('/dashboard', [TeacherSubstitutionController::class, 'dashboard'])
                ->name('teacher.substitutions.dashboard');

            Route::get('/pending', [TeacherSubstitutionController::class, 'pending'])
                ->name('teacher.substitutions.pending');

            Route::get('/suggestions', [TeacherSubstitutionController::class, 'suggestions'])
                ->name('teacher.substitutions.suggestions');

            Route::post('/', [TeacherSubstitutionController::class, 'store'])
                ->name('teacher.substitutions.store');

            Route::post('/{teacherSubstitution}/assign', [TeacherSubstitutionController::class, 'assign'])
                ->name('teacher.substitutions.assign');

            Route::post('/{teacherSubstitution}/approve', [TeacherSubstitutionController::class, 'approve'])
                ->name('teacher.substitutions.approve');

            Route::post('/{teacherSubstitution}/cancel', [TeacherSubstitutionController::class, 'cancel'])
                ->name('teacher.substitutions.cancel');
        });

        Route::post('/sidebar-menus/reorder', [SidebarMenuController::class, 'reorder'])->name('sidebar-menus.reorder');
        Route::apiResource('sidebar-menus', SidebarMenuController::class);

        Route::get('/sections', [SectionController::class, 'index'])
            ->name('sections.index');
        Route::apiResource('academic-years', AcademicYearController::class);

        Route::post('/question-papers/{id}/finalize', [QuestionPaperController::class, 'finalize'])->name('question-papers.finalize');
        Route::post('/question-papers/{id}/reopen', [QuestionPaperController::class, 'reopen'])->name('question-papers.reopen');
        Route::post('/question-papers/{id}/printed', [QuestionPaperController::class, 'markPrinted'])->name('question-papers.printed');
        Route::post('/question-papers/{id}/archive', [QuestionPaperController::class, 'archive'])->name('question-papers.archive');

        Route::apiResource('subject-templates', SubjectTemplateController::class)->names([
            'index' => 'subject-templates',
        ]);

        Route::post('/subject-templates/{subjectTemplate}/apply', [SubjectTemplateController::class, 'apply'])->name('subject-templates.apply');

        Route::get('/question-type-template-masters', [QuestionTypeTemplateController::class, 'masters'])->name('question-type-template-masters');

        Route::apiResource('question-type-templates', QuestionTypeTemplateController::class);

        Route::post('/question-type-templates/{questionTypeTemplate}/apply', [QuestionTypeTemplateController::class, 'apply'])->name('question-type-templates.apply');

        Route::post('/blueprint-import/question-type-template', [BlueprintImportController::class, 'importQuestionTypeTemplate'])->name('blueprint.import.question-type-template');

        Route::post('/blueprint-import/paper-blueprint', [BlueprintImportController::class, 'importPaperBlueprint'])->name('blueprint.import.paper-blueprint');

        Route::post('/blueprint-import/all', [BlueprintImportController::class, 'importAll'])->name('blueprint.import.all');

        Route::get('/blueprint-import/template', [BlueprintImportController::class, 'downloadTemplate'])->name('blueprint.excel');

        Route::get('/teachers/{teacher}/profile', [TeacherProfileController::class, 'show'])->name('teachers.profile.show');

        Route::put('/teachers/{teacher}/profile', [TeacherProfileController::class, 'update'])->name('teachers.profile.update');

        Route::apiResource('ai-paper-generations', AiPaperGeneratorController::class);

        Route::get('/ai-paper-generations/{aiPaperGeneration}/progress', [AiPaperGeneratorController::class, 'progress']);

        Route::post('/ai-generated-questions/{aiGeneratedQuestion}/regenerate', [AiPaperGeneratorController::class, 'regenerateQuestion']);

        Route::post('/ai-generated-questions/{aiGeneratedQuestion}/regenerate-preview', [AiPaperGeneratorController::class, 'regenerateQuestionPreview']);

        Route::post('/ai-generated-questions/{aiGeneratedQuestion}/accept-regenerated', [AiPaperGeneratorController::class, 'acceptRegeneratedQuestion']);

        Route::post('/ai-paper-generations/{aiPaperGeneration}/save-to-question-bank', [
            AiPaperGeneratorController::class,
            'saveToQuestionBank',
        ])->name('ai-paper-generations.save-to-question-bank');
    });

    Route::middleware(['auth:sanctum'])->group(function () {
        Route::get('/ai-paper-generations', [AiPaperGeneratorController::class, 'index'])
            ->name('ai.paper.generator.index');

        Route::post('/ai-paper-generations', [AiPaperGeneratorController::class, 'store'])
            ->name('ai.paper.generator.create');

        Route::get('/ai-paper-generations/{aiPaperGeneration}', [AiPaperGeneratorController::class, 'show'])
            ->name('ai.paper.generator.show');

        Route::delete('/ai-paper-generations/{aiPaperGeneration}', [AiPaperGeneratorController::class, 'destroy'])
            ->name('ai.paper.generator.delete');

        Route::post('/ai-paper-generations/{aiPaperGeneration}/save-to-question-bank', [
            AiPaperGeneratorController::class,
            'saveToQuestionBank',
        ])->name('ai.paper.generator.save.questions');
    });

    Route::middleware('role:teacher')->group(function () {
        Route::get('/teacher/dashboard', [TeacherDashboardController::class, 'index'])
            ->name('teacher.dashboard');

        Route::get('/students', [StudentController::class, 'index'])
            ->name('students.index');
    });

    Route::middleware('role:student')->group(function () {
        Route::get('/profile', function (Request $request) {
            return $request->user();
        })->name('student.profile');
    });
});
