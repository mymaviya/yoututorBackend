<?php

use App\Http\Controllers\Api\Admin\TimetableReportController;
use Illuminate\Support\Facades\Route;

Route::prefix('timetable-reports')
    ->name('timetable.reports.')
    ->group(function () {
        Route::get('/classes/{weeklyTimetable}', [TimetableReportController::class, 'classReport'])
            ->name('classes.show');
        Route::get('/classes/{weeklyTimetable}/excel', [TimetableReportController::class, 'classExcel'])
            ->name('classes.excel');
        Route::get('/classes/{weeklyTimetable}/pdf', [TimetableReportController::class, 'classPdf'])
            ->name('classes.pdf');

        Route::get('/teachers/{teacher}', [TimetableReportController::class, 'teacher'])
            ->name('teachers.show');
        Route::get('/teachers/{teacher}/excel', [TimetableReportController::class, 'teacherExcel'])
            ->name('teachers.excel');
        Route::get('/teachers/{teacher}/pdf', [TimetableReportController::class, 'teacherPdf'])
            ->name('teachers.pdf');

        Route::get('/rooms/{timetableRoom}', [TimetableReportController::class, 'room'])
            ->name('rooms.show');
        Route::get('/rooms/{timetableRoom}/excel', [TimetableReportController::class, 'roomExcel'])
            ->name('rooms.excel');
        Route::get('/rooms/{timetableRoom}/pdf', [TimetableReportController::class, 'roomPdf'])
            ->name('rooms.pdf');

        Route::get('/workload', [TimetableReportController::class, 'workload'])
            ->name('workload');
        Route::get('/conflicts', [TimetableReportController::class, 'conflicts'])
            ->name('conflicts');
    });
