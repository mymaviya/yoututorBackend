<?php

use App\Http\Controllers\Api\Admin\BatchTimetableGeneratorController;
use Illuminate\Support\Facades\Route;

Route::prefix('timetable-batch-generator')
    ->name('timetable.batch.generator.')
    ->group(function () {
        Route::post('/preview', [BatchTimetableGeneratorController::class, 'preview'])
            ->name('preview');
        Route::post('/generate', [BatchTimetableGeneratorController::class, 'generate'])
            ->name('generate');
    });
