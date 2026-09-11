<?php

use App\Http\Controllers\Clubs\CreateClubController;
use App\Http\Controllers\Clubs\DesactivateClubController;
use App\Http\Controllers\Clubs\GetClubController;
use App\Http\Controllers\Clubs\ShowClubController;
use App\Http\Controllers\Clubs\UpdateClubController;
use App\Http\Controllers\Notifications\GetNotificationChannelsController;
use Illuminate\Support\Facades\Route;

Route::prefix('clubs')->middleware('permission')->group(function (): void {
    Route::get('', GetClubController::class)->name('club.collection');
    Route::get('/{id}', ShowClubController::class)->name('club.view');
    Route::post('', CreateClubController::class)->withoutMiddleware('permission')->name('club.create');
    Route::put('{id}', UpdateClubController::class)->name('club.update');
    Route::delete('{id}', DesactivateClubController::class)->name('club.deactivate');

    Route::get('/{club_id}/notification-channels', GetNotificationChannelsController::class)
        ->withoutMiddleware('permission')->name('club.notification_channels');
});
