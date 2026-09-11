<?php

use App\Http\Controllers\Pricing\ChangeCourtPriceStatusController;
use App\Http\Controllers\Pricing\ChangeCourtPromotionStatusController;
use App\Http\Controllers\Pricing\CreateCourtPriceController;
use App\Http\Controllers\Pricing\CreateCourtPromotionController;
use App\Http\Controllers\Pricing\GetCourtPriceController;
use App\Http\Controllers\Pricing\GetCourtPromotionController;
use App\Http\Controllers\Pricing\ShowCourtPriceController;
use App\Http\Controllers\Pricing\ShowCourtPromotionController;
use App\Http\Controllers\Pricing\UpdateCourtPriceController;
use App\Http\Controllers\Pricing\UpdateCourtPromotionController;
use Illuminate\Support\Facades\Route;

Route::prefix('branches/{branch_id}/prices')->middleware('permission')->group(function (): void {
    Route::get('', GetCourtPriceController::class)->name('court_price.collection');
    Route::post('', CreateCourtPriceController::class)->name('court_price.create');
});

Route::prefix('court_prices')->middleware('permission')->group(function (): void {
    Route::get('/{id}', ShowCourtPriceController::class)->name('court_price.view');
    Route::put('/{id}', UpdateCourtPriceController::class)->name('court_price.update');
    Route::patch('/{id}/status', ChangeCourtPriceStatusController::class)->name('court_price.change_status');
});

Route::prefix('court_prices/{court_price_id}/promotions')->middleware('permission')->group(function (): void {
    Route::get('', GetCourtPromotionController::class)->name('court_promotion.collection');
    Route::post('', CreateCourtPromotionController::class)->name('court_promotion.create');
});

Route::prefix('court_promotions')->middleware('permission')->group(function (): void {
    Route::get('/{id}', ShowCourtPromotionController::class)->name('court_promotion.view');
    Route::put('/{id}', UpdateCourtPromotionController::class)->name('court_promotion.update');
    Route::patch('/{id}/status', ChangeCourtPromotionStatusController::class)->name('court_promotion.change_status');
});
