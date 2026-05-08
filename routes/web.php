<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\GradeController;

Route::get('/', function () {
    return view('welcome');
});


