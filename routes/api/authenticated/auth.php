<?php

use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Auth\SendEmailVerificationController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function (): void {
    Route::post('/logout', LogoutController::class)->name('auth.logout');
    Route::post('/email/verification-notification', SendEmailVerificationController::class)
        ->middleware('throttle:6,1')->name('verification.send');
});
