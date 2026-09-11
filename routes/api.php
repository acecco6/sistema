<?php

use Illuminate\Support\Facades\Route;

require __DIR__.'/api/public/auth.php';
require __DIR__.'/api/public/integrations.php';
require __DIR__.'/api/public/reservations.php';

Route::middleware('auth:sanctum')->group(function (): void {
    require __DIR__.'/api/authenticated/auth.php';
    require __DIR__.'/api/authenticated/session.php';
    require __DIR__.'/api/authenticated/clubs.php';
    require __DIR__.'/api/authenticated/customers.php';
    require __DIR__.'/api/authenticated/branches.php';
    require __DIR__.'/api/authenticated/memberships.php';
    require __DIR__.'/api/authenticated/courts.php';
    require __DIR__.'/api/authenticated/pricing.php';
    require __DIR__.'/api/authenticated/fixed_reservations.php';
    require __DIR__.'/api/authenticated/reservations.php';
    require __DIR__.'/api/authenticated/payments.php';
});
