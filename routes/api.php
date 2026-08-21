<?php


use App\Http\Controllers\Auth\{LoginController, LogoutController, RegisterController};
use App\Http\Controllers\Clubs\{CreateClubController, DeleteClubController, GetClubController, ShowClubController, UpdateClubController};
use App\Http\Controllers\Users\ProfileController;
use Illuminate\Support\Facades\Route;



Route::prefix('auth')->group(function () {
    Route::post('/login', LoginController::class);
    Route::post('/register', RegisterController::class);
});

Route::middleware('auth:sanctum')->group(function () {

    Route::prefix('auth')->group(function () {
        Route::post('/logout', LogoutController::class);
    });

    Route::prefix('user')->group(function () {
        Route::get('/', ProfileController::class);
    });

    Route::prefix('clubs')->group(function () {
        Route::get('', GetClubController::class);
        Route::get('/{id}', ShowClubController::class);
        Route::post('', CreateClubController::class);
        Route::put('{id}', UpdateClubController::class);
        Route::delete('{id}', DeleteClubController::class);
    });
});
