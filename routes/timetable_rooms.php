<?php

use App\Http\Controllers\Api\Admin\TimetableRoomController;
use Illuminate\Support\Facades\Route;

Route::prefix('timetable-rooms')
    ->name('timetable.rooms.')
    ->group(function () {
        Route::get('/', [TimetableRoomController::class, 'index'])->name('index');
        Route::post('/', [TimetableRoomController::class, 'store'])->name('store');
        Route::get('/{timetableRoom}', [TimetableRoomController::class, 'show'])->name('show');
        Route::put('/{timetableRoom}', [TimetableRoomController::class, 'update'])->name('update');
        Route::patch('/{timetableRoom}', [TimetableRoomController::class, 'update']);
        Route::delete('/{timetableRoom}', [TimetableRoomController::class, 'destroy'])->name('destroy');
        Route::post('/{timetableRoom}/activate', [TimetableRoomController::class, 'activate'])
            ->name('activate');
        Route::post('/{timetableRoom}/deactivate', [TimetableRoomController::class, 'deactivate'])
            ->name('deactivate');
    });