<?php

use App\Http\Controllers\Backoffice\GetMercadoPagoStatusController;
use App\Http\Controllers\Payments\CompleteRefundController;
use App\Http\Controllers\Payments\CreateRefundController;
use App\Http\Controllers\Payments\GetRefundController;
use App\Http\Controllers\Payments\GetReservationPaymentsController;
use App\Http\Controllers\Payments\ListRefundsController;
use App\Http\Controllers\Payments\MercadoPagoAccounts\ConnectMercadoPagoController;
use App\Http\Controllers\Payments\RegisterManualPaymentController;
use Illuminate\Support\Facades\Route;

Route::prefix('clubs/{club_id}/mercado-pago')->middleware('permission')->group(function (): void {
    Route::post('/connect', ConnectMercadoPagoController::class)->name('club.mercado_pago.connect');
    Route::get('', GetMercadoPagoStatusController::class)->name('club.mercado_pago.view');
});

Route::prefix('reservations/{id}')->middleware('permission')->group(function (): void {
    Route::post('/payments', RegisterManualPaymentController::class)->name('payment.create');
    Route::get('/payments', GetReservationPaymentsController::class)->name('payment.view');
    Route::post('/refunds', CreateRefundController::class)->name('reservation.refund.create');
});

Route::prefix('refunds')->middleware('permission')->group(function (): void {
    Route::get('/{id}', GetRefundController::class)->name('refund.view');
    Route::patch('/{id}/complete', CompleteRefundController::class)->name('refund.complete');
});

Route::get('branches/{branch_id}/refunds', ListRefundsController::class)
    ->middleware('permission')->name('refund.collection');
