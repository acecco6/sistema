<?php


use App\Http\Controllers\Auth\{LoginController, LogoutController, RegisterController};
use App\Http\Controllers\Branches\{CreateBranchController, DesactivateBranchController, GetBranchController, ShowBranchController, UpdateBranchController};
use App\Http\Controllers\Clubs\{CreateClubController, DesactivateClubController, GetClubController, ShowClubController, UpdateClubController};
use App\Http\Controllers\Courts\{CreateCourtController, DeactivateCourtController, GetCourtController, ShowCourtController, UpdateCourtController};
use App\Http\Controllers\Memberships\ChangeMembershipBranchController;
use App\Http\Controllers\Memberships\ChangeMembershipRoleController;
use App\Http\Controllers\Memberships\ChangeMembershipStatusController;
use App\Http\Controllers\Memberships\CreateMembershipController;
use App\Http\Controllers\Users\ProfileController;
use Illuminate\Support\Facades\Route;

// Rutas Publicas de Auth
Route::prefix('auth')->group(function () {
    Route::post('/login', LoginController::class)->name('auth.login');
    Route::post('/register', RegisterController::class)->name('auth.register');
});

Route::middleware('auth:sanctum')->group(function () {


    // Rutas de Auth
    Route::prefix('auth')->group(function () {
        Route::post('/logout', LogoutController::class)->name('auth.logout');
    });


    // Rutas de Usuario
    Route::prefix('user')->group(function () {
        Route::get('/', ProfileController::class)->name('user.view');
    });


    // Rutas de Clubes
    Route::prefix('clubs')->middleware('permission')->group(function () {
        Route::get('', GetClubController::class)->name('club.collection');
        Route::get('/{id}', ShowClubController::class)->name('club.view');
        Route::post('', CreateClubController::class)->withoutMiddleware('permission')->name('club.create');
        Route::put('{id}', UpdateClubController::class)->name('club.update');
        Route::delete('{id}', DesactivateClubController::class)->name('club.deactivate');

        // Rutas de Sucursales por Club (Lectura y Creación)
        Route::get('{club_id}/branches', GetBranchController::class)->name('branch.collection');
        Route::post('{club_id}/branches', CreateBranchController::class)->name('branch.create');
    });

    // Rutas de Sucursales (Actualización, Visualización única, Eliminación)
    Route::prefix('branches')->middleware('permission')->group(function () {
        Route::get('/{id}', ShowBranchController::class)->name('branch.view');
        Route::put('/{id}', UpdateBranchController::class)->name('branch.update');
        Route::delete('/{id}', DesactivateBranchController::class)->name('branch.deactivate');
    });


    // Rutas de Membresias
    Route::prefix('memberships')->middleware('permission')->group(function () {
        Route::post('', CreateMembershipController::class)->name('membership.create');
        Route::patch('{id}/status', ChangeMembershipStatusController::class)->name('membership.change_status');
        Route::patch('{id}/role', ChangeMembershipRoleController::class)->name('membership.change_role');
        Route::patch('{id}/branche', ChangeMembershipBranchController::class)->name('membership.change_branch');
    });

    // Rutas de Courts (Canchas)
    Route::prefix('branches/{branch_id}/courts')->middleware('permission')->group(function () {
        Route::get('', GetCourtController::class)->name('court.collection');
        Route::post('', CreateCourtController::class)->name('court.create');
    });

    Route::prefix('courts')->middleware('permission')->group(function () {
        Route::get('/{id}', ShowCourtController::class)->name('court.view');
        Route::put('/{id}', UpdateCourtController::class)->name('court.update');
        Route::delete('/{id}', DeactivateCourtController::class)->name('court.deactivate');
    });
});
