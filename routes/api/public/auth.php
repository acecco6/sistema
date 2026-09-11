<?php

use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\ResetPasswordController;
use App\Http\Controllers\Auth\VerifyEmailController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function (): void {
    Route::post('/login', LoginController::class)->name('auth.login');
    Route::post('/register', RegisterController::class)->name('auth.register');
    Route::post('/forgot-password', ForgotPasswordController::class)->middleware('throttle:6,1')->name('auth.password.forgot');
    Route::post('/reset-password', ResetPasswordController::class)->middleware('throttle:6,1')->name('auth.password.reset');
    Route::get('/email/verify/{id}/{hash}', VerifyEmailController::class)
        ->middleware(['signed', 'throttle:6,1'])->name('verification.verify');
});
