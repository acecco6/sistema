<?php


use App\Http\Controllers\Auth\{LoginController, LogoutController, RegisterController};
use App\Http\Controllers\Branches\{CreateBranchController, DesactivateBranchController, GetBranchController, ShowBranchController, UpdateBranchController};
use App\Http\Controllers\Clubs\{CreateClubController, DesactivateClubController, GetClubController, ShowClubController, UpdateClubController};
use App\Http\Controllers\Memberships\CreateMembershipController;
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
        Route::delete('{id}', DesactivateClubController::class);

        // Rutas anidadas de Sucursales por Club (Lectura y Creación)
        Route::get('{club_id}/branches', GetBranchController::class);
        Route::post('{club_id}/branches', CreateBranchController::class);
    });

    // Rutas directas de Sucursales (Actualización, Visualización única, Eliminación)
    Route::prefix('branches')->group(function () {
        Route::get('/{id}', ShowBranchController::class);
        Route::put('/{id}', UpdateBranchController::class);
        Route::delete('/{id}', DesactivateBranchController::class);
    });


    Route::prefix('memberships')->group(function () {
        Route::post('', CreateMembershipController::class);
    });
});
