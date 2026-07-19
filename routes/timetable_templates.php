<?php

use App\Http\Controllers\Api\Admin\TimetableTemplateController;
use Illuminate\Support\Facades\Route;

Route::prefix('timetable-templates')
    ->name('timetable.templates.')
    ->group(function () {
        Route::get('/', [TimetableTemplateController::class, 'index'])->name('index');
        Route::post('/', [TimetableTemplateController::class, 'store'])->name('store');

        Route::post('/{timetableTemplate}/activate', [TimetableTemplateController::class, 'activate'])
            ->name('activate');
        Route::post('/{timetableTemplate}/deactivate', [TimetableTemplateController::class, 'deactivate'])
            ->name('deactivate');
        Route::post('/{timetableTemplate}/duplicate', [TimetableTemplateController::class, 'duplicate'])
            ->name('duplicate');

        Route::get('/{timetableTemplate}', [TimetableTemplateController::class, 'show'])->name('show');
        Route::put('/{timetableTemplate}', [TimetableTemplateController::class, 'update'])->name('update');
        Route::patch('/{timetableTemplate}', [TimetableTemplateController::class, 'update']);
        Route::delete('/{timetableTemplate}', [TimetableTemplateController::class, 'destroy'])->name('destroy');
    });
