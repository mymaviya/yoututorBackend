<?php

use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\DashboardController;
use App\Http\Controllers\API\StudentController;
use App\Http\Controllers\API\GradeController;
use App\Http\Controllers\API\SubjectController;
use App\Http\Controllers\API\LessonController;
use App\Http\Controllers\API\QuestionController;
use App\Http\Controllers\API\QuestionPaperController;
use App\Http\Controllers\API\QuestionPaperPdfController;
use App\Http\Controllers\API\TeacherController;
use App\Http\Controllers\API\TeacherReportController;
use App\Http\Controllers\API\TeacherQuestionTaskController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
// Route::get('grades', [GradeController::class, 'index']);

Route::middleware('auth:sanctum')->group(function () {


    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/profile', [AuthController::class, 'update']);



    Route::get('/current-user', function (Request $request) {

        $user = $request->user();

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'contact' => $user->contact,
            'address' => $user->address,
            'role' => $user->role,
            'profile' => $user->profile ? asset('storage/' . $user->profile) : null
        ]);
     });

    // Admin only
    Route::middleware('role:admin')->group(function () {

        Route::get('dashboard', [DashboardController::class, 'index']);

        Route::apiResource('grades', GradeController::class);
        Route::apiResource('subjects', SubjectController::class);
        Route::apiResource('lessons', LessonController::class);
        Route::apiResource('questions', QuestionController::class);
        Route::apiResource('question-papers', QuestionPaperController::class);

        Route::post('/grade_status/{id}', [GradeController::class, 'status']);
        Route::post('/subject_status/{id}', [SubjectController::class, 'status']);
        Route::post('papers/auto-generate', [QuestionPaperController::class, 'autoGenerate']);
        Route::get('question-papers/{id}/pdf',[QuestionPaperPdfController::class, 'download']);

        Route::apiResource('teachers', TeacherController::class);
        Route::get('/reports/teacher-question-paper-progress', [TeacherReportController::class,'questionPaperProgress']);

        Route::apiResource('teacher-question-tasks',TeacherQuestionTaskController::class);


    });

    // For dropdown access (teachers also)
    Route::middleware('role:admin,teacher')->group(function () {
        Route::get('/subjects', [SubjectController::class, 'index']);
        Route::get('/lessons', [LessonController::class, 'index']);

        Route::apiResource('question-papers', QuestionPaperController::class);

    });

    // Teacher
    Route::middleware('role:teacher')->group(function () {
        Route::get('/students', [StudentController::class, 'index']);
    });

    // Student
    Route::middleware('role:student')->group(function () {
        Route::get('/profile', function (Request $request) {
            return $request->user();
        });
    });
});
