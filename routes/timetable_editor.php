<?php

use App\Http\Controllers\Api\Admin\WeeklyTimetableEditorController;
use Illuminate\Support\Facades\Route;

Route::prefix('weekly-timetables/{weeklyTimetable}')
    ->name('weekly.timetables.editor.')
    ->group(function () {
        Route::get('/grid', [WeeklyTimetableEditorController::class, 'grid'])
            ->name('grid');

        Route::post('/entries', [WeeklyTimetableEditorController::class, 'store'])
            ->name('entries.store');

        Route::put('/entries/{timetableEntry}', [WeeklyTimetableEditorController::class, 'update'])
            ->name('entries.update');

        Route::patch('/entries/{timetableEntry}', [WeeklyTimetableEditorController::class, 'update']);

        Route::delete('/entries/{timetableEntry}', [WeeklyTimetableEditorController::class, 'destroy'])
            ->name('entries.destroy');

        Route::put('/grid', [WeeklyTimetableEditorController::class, 'replaceGrid'])
            ->name('grid.replace');
    });
