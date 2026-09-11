<?php

use App\Http\Controllers\Reservations\BookCourtGuestController;
use App\Http\Controllers\Reservations\CancelGuestReservationController;
use App\Http\Controllers\Reservations\GetCourtAvailabilityController;
use App\Http\Controllers\Reservations\GetTipoCourtAvailabilityController;
use App\Http\Controllers\Reservations\ShowGuestReservationController;
use Illuminate\Support\Facades\Route;

Route::prefix('public')->group(function (): void {
    Route::prefix('courts/{court_id}')->group(function (): void {
        Route::post('book', BookCourtGuestController::class)->name('reservation.guest.create');
        Route::get('availability', GetCourtAvailabilityController::class)->name('availability.collection');
    });

    Route::get('branches/{branch_id}/availability', GetTipoCourtAvailabilityController::class)
        ->name('availability.tipo_court.collection');

    Route::prefix('reservations')->group(function (): void {
        Route::get('{token}', ShowGuestReservationController::class)->name('reservation.guest.view');
        Route::patch('{token}/cancel', CancelGuestReservationController::class)->name('reservation.guest.cancel');
    });
});
