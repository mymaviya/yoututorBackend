<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

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

/*
|--------------------------------------------------------------------------
| PUBLIC ROUTES
|--------------------------------------------------------------------------
*/

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

/*
|--------------------------------------------------------------------------
| AUTH ROUTES
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->group(function () {

    /* AUTH USER */

    Route::post('/logout', [AuthController::class, 'logout']);

    Route::get('/current-user', function (Request $request) {

        $user = $request->user();

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'contact' => $user->contact,
            'address' => $user->address,
            'role' => $user->role,
            'profile' => $user->profile
                ? asset('storage/' . $user->profile)
                : null,
        ]);
    });

    /* PROFILE */

    Route::post('/profile/update', [ProfileController::class, 'update']);
    Route::post('/profile/change-password', [ProfileController::class, 'changePassword']);

    // NOTIFICATION

    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount']);

    Route::post('/notifications/{id}/read', [NotificationController::class, 'markAsRead']);
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead']);

    Route::post('/notifications/mark-group-read', [NotificationController::class,'markGroupRead']);



    /*
    |--------------------------------------------------------------------------
    | ADMIN + TEACHER COMMON ROUTES
    |--------------------------------------------------------------------------
    | Important: keep these before admin-only resource routes.
    */

    Route::middleware('role:admin,teacher')->group(function () {

        Route::get('/grades', [GradeController::class, 'index']);
        Route::get('/subjects', [SubjectController::class, 'index']);
        Route::get('/lessons', [LessonController::class, 'index']);
        Route::apiResource('questions', QuestionController::class);
        Route::apiResource('question-papers', QuestionPaperController::class);
        Route::post('/papers/auto-generate', [QuestionPaperController::class, 'autoGenerate']);
        Route::get('/question-papers/{id}/pdf', [QuestionPaperPdfController::class, 'download']);
        Route::get('/teacher/my-question-tasks', [TeacherQuestionTaskController::class, 'myTasks']);

        Route::get('/my-exam-portions', [ExamPortionController::class, 'myPortions']);
        Route::post('/exam-portions/{examPortion}/submit', [ExamPortionController::class, 'submit']);

        Route::post('/papers/generate-from-blueprint', [AutoPaperGeneratorController::class, 'generate']);

        Route::post('/paper-generator/preview', [PaperGeneratorController::class, 'preview']);
        Route::post('/paper-generator/generate', [PaperGeneratorController::class,'generate']);

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

            $assignments = $teacher
                ->assignments()
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

    /*
    |--------------------------------------------------------------------------
    | ADMIN ONLY ROUTES
    |--------------------------------------------------------------------------
    */

    Route::middleware('role:admin')->group(function () {

        Route::get('/dashboard', [DashboardController::class, 'index']);

        Route::apiResource('grades', GradeController::class)->except(['index']);
        Route::apiResource('subjects', SubjectController::class)->except(['index']);
        Route::apiResource('lessons', LessonController::class)->except(['index']);

        Route::post('/grade_status/{id}', [GradeController::class, 'status']);
        Route::post('/subject_status/{id}', [SubjectController::class, 'status']);

        Route::apiResource('teachers', TeacherController::class);

        Route::apiResource('teacher-question-tasks', TeacherQuestionTaskController::class);

        Route::get('/reports/teacher-question-paper-progress', [TeacherReportController::class, 'questionPaperProgress']);
        Route::get('/question-approvals', [QuestionApprovalController::class, 'index']);
        Route::post('/question-approvals/{question}/approve', [QuestionApprovalController::class, 'approve']);
        Route::post('/question-approvals/{question}/reject', [QuestionApprovalController::class, 'reject']);

        Route::apiResource('exam-portions', ExamPortionController::class);
        Route::post('/exam-portions/{examPortion}/approve', [ExamPortionController::class, 'approve']);
        Route::post('/exam-portions/{examPortion}/reject', [ExamPortionController::class, 'reject']);

        Route::apiResource('exam-names', ExamNameController::class);
        Route::post('/exam-names/{examName}/status', [ExamNameController::class, 'status']);


        Route::get('/paper-blueprints/dropdown', [PaperBlueprintController::class, 'dropdown']);
        Route::post('/paper-blueprints/{id}/status', [PaperBlueprintController::class, 'status']);
        Route::post('/paper-blueprints/{id}/copy', [PaperBlueprintController::class, 'copy']);
        Route::apiResource('paper-blueprints', PaperBlueprintController::class);

        // QUESTION TYPE IMPORTS
        Route::post('/question-types/{questionType}/status', [QuestionTypeController::class, 'status']);
        Route::post('/question-types/import', [QuestionTypeController::class, 'import']);
        Route::get('/question-types/template', [QuestionTypeController::class, 'downloadTemplate']);
        Route::apiResource('question-types', QuestionTypeController::class);

    });

    /*
    |--------------------------------------------------------------------------
    | TEACHER ONLY ROUTES
    |--------------------------------------------------------------------------
    */

    Route::middleware('role:teacher')->group(function () {

        Route::get('/teacher/dashboard', [TeacherDashboardController::class, 'index']);
        Route::get('/students', [StudentController::class, 'index']);
    });

    /*
    |--------------------------------------------------------------------------
    | STUDENT ONLY ROUTES
    |--------------------------------------------------------------------------
    */

    Route::middleware('role:student')->group(function () {

        Route::get('/profile', function (Request $request) {
            return $request->user();
        });
    });
});
