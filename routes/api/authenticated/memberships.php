<?php

use App\Http\Controllers\Backoffice\ListMembershipsController;
use App\Http\Controllers\Backoffice\SearchUsersController;
use App\Http\Controllers\Backoffice\SearchUsersLegacyController;
use App\Http\Controllers\Backoffice\ShowMembershipController;
use App\Http\Controllers\Memberships\ChangeMembershipBranchController;
use App\Http\Controllers\Memberships\ChangeMembershipRoleController;
use App\Http\Controllers\Memberships\ChangeMembershipStatusController;
use App\Http\Controllers\Memberships\CreateMembershipController;
use Illuminate\Support\Facades\Route;

Route::prefix('clubs/{club_id}')->middleware('permission')->group(function (): void {
    Route::get('/memberships', ListMembershipsController::class)->name('membership.collection');
    Route::get('/users', SearchUsersController::class)->name('user.collection');
});

Route::prefix('memberships')->middleware('permission')->group(function (): void {
    Route::get('{id}', ShowMembershipController::class)->name('membership.view');
    Route::post('', CreateMembershipController::class)->name('membership.create');
    Route::patch('{id}/status', ChangeMembershipStatusController::class)->name('membership.change_status');
    Route::patch('{id}/role', ChangeMembershipRoleController::class)->name('membership.change_role');
    Route::patch('{id}/branch', ChangeMembershipBranchController::class)->name('membership.change_branch');
    Route::patch('{id}/branche', ChangeMembershipBranchController::class)->name('membership.change_branch.legacy');
});

Route::get('users', SearchUsersLegacyController::class)
    ->middleware('permission')->name('user.collection.legacy');
