<?php

use App\Http\Controllers\Backoffice\GetCourtIntervalController;
use App\Http\Controllers\Backoffice\GetDashboardController;
use App\Http\Controllers\Backoffice\UpdateCourtIntervalController;
use App\Http\Controllers\Branches\CreateBranchController;
use App\Http\Controllers\Branches\DesactivateBranchController;
use App\Http\Controllers\Branches\GetBranchController;
use App\Http\Controllers\Branches\ShowBranchController;
use App\Http\Controllers\Branches\UpdateBranchController;
use Illuminate\Support\Facades\Route;

Route::prefix('clubs/{club_id}/branches')->middleware('permission')->group(function (): void {
    Route::get('', GetBranchController::class)->name('branch.collection');
    Route::post('', CreateBranchController::class)->name('branch.create');
});

Route::prefix('branches')->middleware('permission')->group(function (): void {
    Route::get('/{id}', ShowBranchController::class)->name('branch.view');
    Route::put('/{id}', UpdateBranchController::class)->name('branch.update');
    Route::delete('/{id}', DesactivateBranchController::class)->name('branch.deactivate');
});

Route::prefix('branches/{branch_id}/court-types/{court_type_id}/interval')
    ->middleware('permission')
    ->group(function (): void {
        Route::get('', GetCourtIntervalController::class)->name('court_interval.view');
        Route::patch('', UpdateCourtIntervalController::class)->name('court_interval.update');
    });

Route::get('branches/{branch_id}/dashboard', GetDashboardController::class)
    ->middleware('permission')->name('dashboard.view');
