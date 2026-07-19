<?php

use App\Http\Controllers\Api\Admin\TimetableRuleController;
use Illuminate\Support\Facades\Route;

Route::prefix('timetable-rules')
    ->name('timetable.rules.')
    ->group(function () {
        Route::get('/', [TimetableRuleController::class, 'index'])
            ->name('index');

        Route::post('/', [TimetableRuleController::class, 'store'])
            ->name('store');

        Route::post('/{timetableRule}/activate', [TimetableRuleController::class, 'activate'])
            ->name('activate');

        Route::post('/{timetableRule}/deactivate', [TimetableRuleController::class, 'deactivate'])
            ->name('deactivate');

        Route::post('/{timetableRule}/duplicate', [TimetableRuleController::class, 'duplicate'])
            ->name('duplicate');

        Route::get('/{timetableRule}', [TimetableRuleController::class, 'show'])
            ->name('show');

        Route::match(['put', 'patch'], '/{timetableRule}', [TimetableRuleController::class, 'update'])
            ->name('update');

        Route::delete('/{timetableRule}', [TimetableRuleController::class, 'destroy'])
            ->name('destroy');
    });
