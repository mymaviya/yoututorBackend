<?php

use App\Http\Controllers\Api\Admin\OptimizedTimetableGeneratorController;
use Illuminate\Support\Facades\Route;

Route::prefix('timetable-optimizer')
    ->name('timetable.optimizer.')
    ->group(function () {
        Route::post('/preview', [OptimizedTimetableGeneratorController::class, 'preview'])
            ->name('preview');

        Route::post('/generate', [OptimizedTimetableGeneratorController::class, 'generate'])
            ->name('generate');
    });
