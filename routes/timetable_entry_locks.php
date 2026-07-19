<?php

use App\Http\Controllers\Api\Admin\TimetableEntryLockController;
use Illuminate\Support\Facades\Route;

Route::prefix('timetable-entry-locks')
    ->name('timetable.entry.locks.')
    ->group(function () {
        Route::get('/', [TimetableEntryLockController::class, 'index'])->name('index');
        Route::post('/bulk-lock', [TimetableEntryLockController::class, 'bulkLock'])->name('bulk-lock');
        Route::post('/bulk-unlock', [TimetableEntryLockController::class, 'bulkUnlock'])->name('bulk-unlock');
        Route::post('/{timetableEntry}/lock', [TimetableEntryLockController::class, 'lock'])->name('lock');
        Route::post('/{timetableEntry}/unlock', [TimetableEntryLockController::class, 'unlock'])->name('unlock');
    });
