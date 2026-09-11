<?php

use App\Http\Controllers\Reservations\FixedReservations\CreateFixedReservationController;
use App\Http\Controllers\Reservations\FixedReservations\DesactivateFixedReservationController;
use App\Http\Controllers\Reservations\FixedReservations\GetFixedReservationConflictsController;
use App\Http\Controllers\Reservations\FixedReservations\GetFixedReservationsController;
use App\Http\Controllers\Reservations\FixedReservations\ResolveFixedReservationConflictController;
use App\Http\Controllers\Reservations\FixedReservations\ShowFixedReservationController;
use Illuminate\Support\Facades\Route;

Route::prefix('clubs/{club_id}/fixed-reservations')->middleware('permission')->group(function (): void {
    Route::get('', GetFixedReservationsController::class)->name('fixed_reservation.collection');
    Route::post('', CreateFixedReservationController::class)->name('fixed_reservation.create');
});

Route::get('clubs/{club_id}/fixed-reservation-conflicts', GetFixedReservationConflictsController::class)
    ->middleware('permission')->name('fixed_reservation_conflict.collection');

Route::prefix('fixed-reservations')->middleware('permission')->group(function (): void {
    Route::get('/{id}', ShowFixedReservationController::class)->name('fixed_reservation.view');
    Route::patch('/{id}/deactivate', DesactivateFixedReservationController::class)->name('fixed_reservation.deactivate');
    Route::patch('/{id}/desactivate', DesactivateFixedReservationController::class)->name('fixed_reservation.deactivate.legacy');
});

Route::patch('fixed-reservation-conflicts/{id}/resolve', ResolveFixedReservationConflictController::class)
    ->middleware('permission')->name('fixed_reservation_conflict.resolve');
