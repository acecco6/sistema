<?php

use App\Http\Controllers\Backoffice\GetRolePermissionsController;
use App\Http\Controllers\Backoffice\GetSessionContextController;
use App\Http\Controllers\Backoffice\ListCourtTypesController;
use App\Http\Controllers\Backoffice\ListRolesController;
use App\Http\Controllers\Users\ProfileController;
use Illuminate\Support\Facades\Route;

Route::prefix('user')->group(function (): void {
    Route::get('/', ProfileController::class)->name('user.view');
});

Route::get('me/context', GetSessionContextController::class)->name('session.context');
Route::get('roles', ListRolesController::class)->name('role.collection');
Route::get('roles/{id}/permissions', GetRolePermissionsController::class)->name('role.permissions');
Route::get('court-types', ListCourtTypesController::class)->name('court_type.collection');
