<?php

use App\Http\Controllers\Api\Admin\TimetableLifecycleController;
use Illuminate\Support\Facades\Route;

Route::prefix('weekly-timetables')
    ->name('weekly.timetables.')
    ->group(function () {
        Route::get('/', [TimetableLifecycleController::class, 'index'])->name('index');
        Route::post('/{weeklyTimetable}/publish', [TimetableLifecycleController::class, 'publish'])->name('publish');
        Route::post('/{weeklyTimetable}/archive', [TimetableLifecycleController::class, 'archive'])->name('archive');
        Route::post('/{weeklyTimetable}/restore', [TimetableLifecycleController::class, 'restore'])->name('restore');
        Route::post('/{weeklyTimetable}/versions', [TimetableLifecycleController::class, 'createVersion'])->name('versions.store');
    });
