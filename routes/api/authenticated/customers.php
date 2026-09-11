<?php

use App\Http\Controllers\Customers\ChangeCustomerStatusController;
use App\Http\Controllers\Customers\CreateCustomerController;
use App\Http\Controllers\Customers\ListCustomersController;
use App\Http\Controllers\Customers\ShowCustomerController;
use App\Http\Controllers\Customers\UpdateCustomerController;
use Illuminate\Support\Facades\Route;

Route::prefix('clubs/{club_id}/customers')->middleware('permission')->group(function (): void {
    Route::get('', ListCustomersController::class)->name('customer.collection');
    Route::post('', CreateCustomerController::class)->name('customer.create');
    Route::get('/{id}', ShowCustomerController::class)->name('customer.view');
    Route::put('/{id}', UpdateCustomerController::class)->name('customer.update');
    Route::patch('/{id}/status', ChangeCustomerStatusController::class)->name('customer.change_status');
});
