<?php

use App\Http\Controllers\Api\Admin\TimetableRuleController;
use Illuminate\Support\Facades\Route;

Route::prefix('timetable-rules')
    ->name('timetable.rules.')
    ->group(function () {
        Route::post('/{timetableRule}/activate', [TimetableRuleController::class, 'activate'])
            ->name('activate');

        Route::post('/{timetableRule}/deactivate', [TimetableRuleController::class, 'deactivate'])
            ->name('deactivate');

        Route::post('/{timetableRule}/duplicate', [TimetableRuleController::class, 'duplicate'])
            ->name('duplicate');

        Route::apiResource('/', TimetableRuleController::class)
            ->parameters(['' => 'timetableRule'])
            ->names([
                'index' => 'index',
                'store' => 'store',
                'show' => 'show',
                'update' => 'update',
                'destroy' => 'destroy',
            ]);
    });
