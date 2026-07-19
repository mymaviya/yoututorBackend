<?php

use App\Http\Controllers\Api\Admin\AutomaticTimetableGeneratorController;
use Illuminate\Support\Facades\Route;

Route::prefix('timetable-generator')
    ->name('timetable.generator.')
    ->group(function () {
        Route::post('/preview', [AutomaticTimetableGeneratorController::class, 'preview'])
            ->name('preview');

        Route::post('/generate', [AutomaticTimetableGeneratorController::class, 'generate'])
            ->name('generate');
    });
