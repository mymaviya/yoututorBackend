<?php

use App\Http\Controllers\Api\Admin\ParallelGroupController;
use Illuminate\Support\Facades\Route;

Route::prefix('parallel-groups')
    ->name('parallel.groups.')
    ->group(function () {
        Route::get('/', [ParallelGroupController::class, 'index'])->name('index');
        Route::post('/', [ParallelGroupController::class, 'store'])->name('store');
        Route::get('/{parallelGroup}', [ParallelGroupController::class, 'show'])->name('show');
        Route::put('/{parallelGroup}', [ParallelGroupController::class, 'update'])->name('update');
        Route::patch('/{parallelGroup}', [ParallelGroupController::class, 'update']);
        Route::delete('/{parallelGroup}', [ParallelGroupController::class, 'destroy'])->name('destroy');
        Route::post('/{parallelGroup}/activate', [ParallelGroupController::class, 'activate'])->name('activate');
        Route::post('/{parallelGroup}/deactivate', [ParallelGroupController::class, 'deactivate'])->name('deactivate');
    });
