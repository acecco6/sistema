<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Application\Auth\Controllers\LoginController;
use App\Application\Auth\Controllers\LogoutController;
use App\Application\Auth\Controllers\RegisterController;


Route::prefix('auth')->group(function () {
    Route::post('/login', LoginController::class);
    Route::post('/register', RegisterController::class);
});

Route::middleware('auth:sanctum')->group(function () {

    Route::prefix('auth')->group(function () {
        Route::post('/logout', LogoutController::class);
    });

    Route::prefix('user')->group(function () {
        Route::get('/', \App\Application\Users\Controllers\ProfileController::class);
    });
});
