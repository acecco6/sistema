<?php

use App\Http\Controllers\Reservations\BookCourtAuthenticatedController;
use App\Http\Controllers\Reservations\CancelCustomerReservationController;
use App\Http\Controllers\Reservations\CancelReservationController;
use App\Http\Controllers\Reservations\ConfirmReservationController;
use App\Http\Controllers\Reservations\CreateReservationController;
use App\Http\Controllers\Reservations\GetBranchReservationsController;
use App\Http\Controllers\Reservations\GetCourtReservationsController;
use App\Http\Controllers\Reservations\GetCustomerReservationsController;
use App\Http\Controllers\Reservations\ShowCustomerReservationController;
use App\Http\Controllers\Reservations\ShowReservationController;
use Illuminate\Support\Facades\Route;

Route::prefix('courts/{court_id}/reservations')->middleware('permission')->group(function (): void {
    Route::get('', GetCourtReservationsController::class)->name('reservation.collection');
    Route::post('', CreateReservationController::class)->name('reservation.create');
});

Route::get('branches/{branch_id}/reservations', GetBranchReservationsController::class)
    ->middleware('permission')->name('reservation.branch.collection');

Route::post('courts/{court_id}/book', BookCourtAuthenticatedController::class)
    ->name('reservation.customer.create');

Route::prefix('me/reservations')->group(function (): void {
    Route::get('', GetCustomerReservationsController::class)->name('reservation.customer.collection');
    Route::get('/{id}', ShowCustomerReservationController::class)->name('reservation.customer.view');
    Route::patch('/{id}/cancel', CancelCustomerReservationController::class)->name('reservation.customer.cancel');
});

Route::prefix('reservations')->middleware('permission')->group(function (): void {
    Route::get('{id}', ShowReservationController::class)->name('reservation.view');
    Route::patch('{id}/cancel', CancelReservationController::class)->name('reservation.cancel');
    Route::patch('{id}/confirm', ConfirmReservationController::class)->name('reservation.confirm');
});
