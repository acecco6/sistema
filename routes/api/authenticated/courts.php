<?php

use App\Http\Controllers\Courts\CreateCourtController;
use App\Http\Controllers\Courts\DeactivateCourtController;
use App\Http\Controllers\Courts\GetCourtController;
use App\Http\Controllers\Courts\ShowCourtController;
use App\Http\Controllers\Courts\UpdateCourtController;
use Illuminate\Support\Facades\Route;

Route::prefix('branches/{branch_id}/courts')->middleware('permission')->group(function (): void {
    Route::get('', GetCourtController::class)->name('court.collection');
    Route::post('', CreateCourtController::class)->name('court.create');
});

Route::prefix('courts')->middleware('permission')->group(function (): void {
    Route::get('/{id}', ShowCourtController::class)->name('court.view');
    Route::put('/{id}', UpdateCourtController::class)->name('court.update');
    Route::delete('/{id}', DeactivateCourtController::class)->name('court.deactivate');
});
