<?php

use App\Http\Controllers\Api\Admin\TimetableGenerationRunController;
use Illuminate\Support\Facades\Route;

Route::prefix('timetable-generation-runs')
    ->name('timetable.generation.runs.')
    ->group(function () {
        Route::get('/', [TimetableGenerationRunController::class, 'index'])
            ->name('index');
        Route::get('/{timetableGenerationRun}', [TimetableGenerationRunController::class, 'show'])
            ->name('show');
        Route::get('/{timetableGenerationRun}/conflicts', [TimetableGenerationRunController::class, 'conflicts'])
            ->name('conflicts');
        Route::post('/{timetableGenerationRun}/retry', [TimetableGenerationRunController::class, 'retry'])
            ->name('retry');
    });
