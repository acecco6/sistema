<?php


use App\Http\Controllers\Auth\{LoginController, LogoutController, RegisterController};
use App\Http\Controllers\Branches\{CreateBranchController, DesactivateBranchController, GetBranchController, ShowBranchController, UpdateBranchController};
use App\Http\Controllers\Clubs\{CreateClubController, DesactivateClubController, GetClubController, ShowClubController, UpdateClubController};
use App\Http\Controllers\Courts\{CreateCourtController, DeactivateCourtController, GetCourtController, ShowCourtController, UpdateCourtController};
use App\Http\Controllers\Memberships\{ChangeMembershipBranchController, ChangeMembershipRoleController, ChangeMembershipStatusController, CreateMembershipController};
use App\Http\Controllers\Payments\MercadoPagoWebhookController;
use App\Http\Controllers\Payments\RegisterManualPaymentController;
use App\Http\Controllers\Pricing\{ChangeCourtPriceStatusController, ChangeCourtPromotionStatusController, CreateCourtPriceController, CreateCourtPromotionController, GetCourtPriceController, GetCourtPromotionController, ShowCourtPriceController, ShowCourtPromotionController, UpdateCourtPriceController, UpdateCourtPromotionController};
use App\Http\Controllers\Reservations\{BookCourtAuthenticatedController, BookCourtGuestController, CancelCustomerReservationController, CancelReservationController, ConfirmReservationController, CreateReservationController, GetCourtAvailabilityController, GetCourtReservationsController, GetTipoCourtAvailabilityController, ShowReservationController};
use App\Http\Controllers\Reservations\CancelGuestReservationController;
use App\Http\Controllers\Reservations\GetCustomerReservationsController;
use App\Http\Controllers\Reservations\ShowCustomerReservationController;
use App\Http\Controllers\Reservations\ShowGuestReservationController;
use App\Http\Controllers\Users\ProfileController;
use Illuminate\Support\Facades\Route;

// Rutas Publicas de Auth
Route::prefix('auth')->group(function () {
    Route::post('/login', LoginController::class)->name('auth.login');
    Route::post('/register', RegisterController::class)->name('auth.register');
});

Route::post('/webhooks/mercadopago', MercadoPagoWebhookController::class)->name('webhook.mercadopago');

// Rutas Publicas de Reservas para Invitados
Route::prefix('public')->group(function () {


    Route::prefix('courts/{court_id}')->group(function () {
        // Crear Reserva para invitado
        Route::post('book', BookCourtGuestController::class)->name('reservation.guest.create');
        // Rutas de Disponibilidad por Cancha
        Route::get('availability', GetCourtAvailabilityController::class)->name('availability.collection');
    });

    // Rutas de Disponibilidad por Tipo de Cancha
    Route::get('branches/{branch_id}/availability', GetTipoCourtAvailabilityController::class)->name('availability.tipo_court.collection');

    Route::prefix('reservations')->group(function () {
        // Ver reserva por Token
        Route::get('{token}', ShowGuestReservationController::class)->name('reservation.guest.view');
        // Cancelar reserva por Token
        Route::patch('{token}/cancel', CancelGuestReservationController::class)->name('reservation.guest.cancel');
    });
});

Route::middleware('auth:sanctum')->group(function () {


    // Rutas de Auth
    Route::prefix('auth')->group(function () {
        Route::post('/logout', LogoutController::class)->name('auth.logout');
    });


    // Rutas de Usuario
    Route::prefix('user')->group(function () {
        Route::get('/', ProfileController::class)->name('user.view');
    });


    // Rutas de Clubes
    Route::prefix('clubs')->middleware('permission')->group(function () {
        Route::get('', GetClubController::class)->name('club.collection');
        Route::get('/{id}', ShowClubController::class)->name('club.view');
        Route::post('', CreateClubController::class)->withoutMiddleware('permission')->name('club.create');
        Route::put('{id}', UpdateClubController::class)->name('club.update');
        Route::delete('{id}', DesactivateClubController::class)->name('club.deactivate');

        // Rutas de Sucursales por Club (Lectura y Creación)
        Route::get('{club_id}/branches', GetBranchController::class)->name('branch.collection');
        Route::post('{club_id}/branches', CreateBranchController::class)->name('branch.create');
    });

    // Rutas de Sucursales (Actualización, Visualización única, Eliminación)
    Route::prefix('branches')->middleware('permission')->group(function () {
        Route::get('/{id}', ShowBranchController::class)->name('branch.view');
        Route::put('/{id}', UpdateBranchController::class)->name('branch.update');
        Route::delete('/{id}', DesactivateBranchController::class)->name('branch.deactivate');
    });


    // Rutas de Membresias
    Route::prefix('memberships')->middleware('permission')->group(function () {
        Route::post('', CreateMembershipController::class)->name('membership.create');
        Route::patch('{id}/status', ChangeMembershipStatusController::class)->name('membership.change_status');
        Route::patch('{id}/role', ChangeMembershipRoleController::class)->name('membership.change_role');
        Route::patch('{id}/branche', ChangeMembershipBranchController::class)->name('membership.change_branch');
    });

    // Rutas de Courts (Canchas) por Sucursal (Lectura y Creación)
    Route::prefix('branches/{branch_id}/courts')->middleware('permission')->group(function () {
        Route::get('', GetCourtController::class)->name('court.collection');
        Route::post('', CreateCourtController::class)->name('court.create');
    });

    // Rutas de Courts (Canchas) Individuales
    Route::prefix('courts')->middleware('permission')->group(function () {
        Route::get('/{id}', ShowCourtController::class)->name('court.view');
        Route::put('/{id}', UpdateCourtController::class)->name('court.update');
        Route::delete('/{id}', DeactivateCourtController::class)->name('court.deactivate');
    });


    // Rutas de Precios por Sucursal (Lectura y Creación)
    Route::prefix('branches/{branch_id}/prices')->middleware('permission')->group(function () {
        Route::get('', GetCourtPriceController::class)->name('court_price.collection');
        Route::post('', CreateCourtPriceController::class)->name('court_price.create');
    });


    // Rutas individuales de Precios (Actualización, Visualización única, Cambio de estado)
    Route::prefix('court_prices')->middleware('permission')->group(function () {
        Route::get('/{id}', ShowCourtPriceController::class)->name('court_price.view');
        Route::put('/{id}', UpdateCourtPriceController::class)->name('court_price.update');
        Route::patch('/{id}/status', ChangeCourtPriceStatusController::class)->name('court_price.change_status');
    });


    // Rutas de Promociones por Precio (Lectura y Creación)
    Route::prefix('court_prices/{court_price_id}/promotions')->middleware('permission')->group(function () {
        Route::get('', GetCourtPromotionController::class)->name('court_promotion.collection');
        Route::post('', CreateCourtPromotionController::class)->name('court_promotion.create');
    });


    // Rutas individuales de Promociones (Actualización, Visualización única, Cambio de estado)
    Route::prefix('court_promotions')->middleware('permission')->group(function () {
        Route::get('/{id}', ShowCourtPromotionController::class)->name('court_promotion.view');
        Route::put('/{id}', UpdateCourtPromotionController::class)->name('court_promotion.update');
        Route::patch('/{id}/status', ChangeCourtPromotionStatusController::class)->name('court_promotion.change_status');
    });


    // Rutas de Reservas por Cancha (Creación para personal)
    Route::prefix('courts/{court_id}/reservations')->middleware('permission')->group(function () {
        Route::get('', GetCourtReservationsController::class)->name('reservation.collection');
        Route::post('', CreateReservationController::class)->name('reservation.create');
    });


    /*
    |--------------------------------------------------------------------------
    | Reserva para cliente autenticado
    |--------------------------------------------------------------------------
    |
    | Requiere auth:sanctum porque ya estamos dentro
    | del grupo autenticado.
    |
    | NO requiere el middleware permission porque
    | el cliente reserva solamente para sí mismo.
    |
    */

    Route::post('courts/{court_id}/book', BookCourtAuthenticatedController::class)->name('reservation.customer.create');
    /*
    |--------------------------------------------------------------------------
    | Reservas del cliente autenticado
    |--------------------------------------------------------------------------
    |
    | No utilizan middleware permission.
    | La autorización se realiza por ownership.
    |
    */
    Route::prefix('me/reservations')->group(function () {
        Route::get('', GetCustomerReservationsController::class)->name('reservation.customer.collection');
        Route::get('/{id}', ShowCustomerReservationController::class)->name('reservation.customer.view');
        Route::patch('/{id}/cancel', CancelCustomerReservationController::class)->name('reservation.customer.cancel');
    });

    // Rutas de Reservas individuales
    Route::prefix('reservations')->middleware('permission')->group(function () {
        Route::get('/{id}', ShowReservationController::class)->name('reservation.view');
        Route::patch('/{id}/cancel', CancelReservationController::class)->name('reservation.cancel');
        Route::patch('/{id}/confirm', ConfirmReservationController::class)->name('reservation.confirm');
        Route::post('{id}/payments', RegisterManualPaymentController::class)->name('payment.create');
    });
});
