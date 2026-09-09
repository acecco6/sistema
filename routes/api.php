<?php


use App\Http\Controllers\Auth\{LoginController, LogoutController, RegisterController, SendEmailVerificationController, VerifyEmailController};
use App\Http\Controllers\Customers\{ChangeCustomerStatusController, CreateCustomerController, ListCustomersController, ShowCustomerController, UpdateCustomerController};
use App\Http\Controllers\Branches\{CreateBranchController, DesactivateBranchController, GetBranchController, ShowBranchController, UpdateBranchController};
use App\Http\Controllers\Backoffice\{GetCourtIntervalController, GetDashboardController, GetMercadoPagoStatusController, GetRolePermissionsController, GetSessionContextController, ListCourtTypesController, ListMembershipsController, ListRolesController, SearchUsersController, SearchUsersLegacyController, ShowMembershipController, UpdateCourtIntervalController};
use App\Http\Controllers\Clubs\{CreateClubController, DesactivateClubController, GetClubController, ShowClubController, UpdateClubController};
use App\Http\Controllers\Courts\{CreateCourtController, DeactivateCourtController, GetCourtController, ShowCourtController, UpdateCourtController};
use App\Http\Controllers\Memberships\{ChangeMembershipBranchController, ChangeMembershipRoleController, ChangeMembershipStatusController, CreateMembershipController};
use App\Http\Controllers\Payments\CompleteRefundController;
use App\Http\Controllers\Payments\CreateRefundController;
use App\Http\Controllers\Payments\GetRefundController;
use App\Http\Controllers\Payments\GetReservationPaymentsController;
use App\Http\Controllers\Payments\ListRefundsController;
use App\Http\Controllers\Payments\MercadoPagoAccounts\ConnectMercadoPagoController;
use App\Http\Controllers\Payments\MercadoPagoAccounts\MercadoPagoOAuthCallbackController;
use App\Http\Controllers\Payments\MercadoPagoWebhookController;
use App\Http\Controllers\Payments\RegisterManualPaymentController;
use App\Http\Controllers\Pricing\{ChangeCourtPriceStatusController, ChangeCourtPromotionStatusController, CreateCourtPriceController, CreateCourtPromotionController, GetCourtPriceController, GetCourtPromotionController, ShowCourtPriceController, ShowCourtPromotionController, UpdateCourtPriceController, UpdateCourtPromotionController};
use App\Http\Controllers\Reservations\{BookCourtAuthenticatedController, BookCourtGuestController, CancelCustomerReservationController, CancelReservationController, ConfirmReservationController, CreateReservationController, GetCourtAvailabilityController, GetCourtReservationsController, GetTipoCourtAvailabilityController, ShowReservationController};
use App\Http\Controllers\Reservations\CancelGuestReservationController;
use App\Http\Controllers\Reservations\FixedReservations\CreateFixedReservationController;
use App\Http\Controllers\Reservations\FixedReservations\DesactivateFixedReservationController;
use App\Http\Controllers\Reservations\FixedReservations\GetFixedReservationConflictsController;
use App\Http\Controllers\Reservations\FixedReservations\GetFixedReservationsController;
use App\Http\Controllers\Reservations\FixedReservations\ResolveFixedReservationConflictController;
use App\Http\Controllers\Reservations\FixedReservations\ShowFixedReservationController;
use App\Http\Controllers\Reservations\GetBranchReservationsController;
use App\Http\Controllers\Reservations\GetCustomerReservationsController;
use App\Http\Controllers\Reservations\ShowCustomerReservationController;
use App\Http\Controllers\Reservations\ShowGuestReservationController;
use App\Http\Controllers\Users\ProfileController;
use Illuminate\Support\Facades\Route;

// Rutas Publicas de Auth
Route::prefix('auth')->group(function () {
    Route::post('/login', LoginController::class)->name('auth.login');
    Route::post('/register', RegisterController::class)->name('auth.register');
    Route::get('/email/verify/{id}/{hash}', VerifyEmailController::class)
        ->middleware(['signed', 'throttle:6,1'])->name('verification.verify');
});

Route::get('/integrations/mercado-pago/callback', MercadoPagoOAuthCallbackController::class)->name('mercado_pago.oauth.callback');

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
        Route::post('/email/verification-notification', SendEmailVerificationController::class)
            ->middleware('throttle:6,1')->name('verification.send');
    });


    // Rutas de Usuario
    Route::prefix('user')->group(function () {
        Route::get('/', ProfileController::class)->name('user.view');
    });

    Route::get('me/context', GetSessionContextController::class)->name('session.context');

    Route::get('roles', ListRolesController::class)->name('role.collection');
    Route::get('roles/{id}/permissions', GetRolePermissionsController::class)->name('role.permissions');
    Route::get('court-types', ListCourtTypesController::class)->name('court_type.collection');


    // Rutas de Clubes
    Route::prefix('clubs')->middleware('permission')->group(function () {
        Route::get('', GetClubController::class)->name('club.collection');
        Route::get('/{id}', ShowClubController::class)->name('club.view');
        Route::post('', CreateClubController::class)->withoutMiddleware('permission')->name('club.create');
        Route::put('{id}', UpdateClubController::class)->name('club.update');
        Route::delete('{id}', DesactivateClubController::class)->name('club.deactivate');

        Route::post('/{club_id}/mercado-pago/connect', ConnectMercadoPagoController::class)->name('club.mercado_pago.connect');
        Route::get('/{club_id}/mercado-pago', GetMercadoPagoStatusController::class)->name('club.mercado_pago.view');

        Route::get('/{club_id}/memberships', ListMembershipsController::class)->name('membership.collection');
        Route::get('/{club_id}/users', SearchUsersController::class)->name('user.collection');

        Route::get('/{club_id}/customers', ListCustomersController::class)->name('customer.collection');
        Route::post('/{club_id}/customers', CreateCustomerController::class)->name('customer.create');
        Route::get('/{club_id}/customers/{id}', ShowCustomerController::class)->name('customer.view');
        Route::put('/{club_id}/customers/{id}', UpdateCustomerController::class)->name('customer.update');
        Route::patch('/{club_id}/customers/{id}/status', ChangeCustomerStatusController::class)->name('customer.change_status');

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
        Route::get('{id}', ShowMembershipController::class)->name('membership.view');
        Route::post('', CreateMembershipController::class)->name('membership.create');
        Route::patch('{id}/status', ChangeMembershipStatusController::class)->name('membership.change_status');
        Route::patch('{id}/role', ChangeMembershipRoleController::class)->name('membership.change_role');
        Route::patch('{id}/branch', ChangeMembershipBranchController::class)->name('membership.change_branch');
        Route::patch('{id}/branche', ChangeMembershipBranchController::class)->name('membership.change_branch.legacy');
    });

    Route::get('users', SearchUsersLegacyController::class)
        ->middleware('permission')
        ->name('user.collection.legacy');

    Route::prefix('branches/{branch_id}/court-types/{court_type_id}/interval')
        ->middleware('permission')
        ->group(function () {
            Route::get('', GetCourtIntervalController::class)->name('court_interval.view');
            Route::patch('', UpdateCourtIntervalController::class)->name('court_interval.update');
        });

    Route::get('branches/{branch_id}/dashboard', GetDashboardController::class)
        ->middleware('permission')
        ->name('dashboard.view');

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

    Route::prefix('clubs/{club_id}/fixed-reservations')->middleware('permission')->group(function () {
        Route::get('', GetFixedReservationsController::class)->name('fixed_reservation.collection');
        Route::post('', CreateFixedReservationController::class)->name('fixed_reservation.create');
    });

    Route::prefix('clubs/{club_id}/fixed-reservation-conflicts')->middleware('permission')->group(function () {
        Route::get('', GetFixedReservationConflictsController::class)->name('fixed_reservation_conflict.collection');
    });

    Route::prefix('fixed-reservations')->middleware('permission')->group(function () {
        Route::get('/{id}', ShowFixedReservationController::class)->name('fixed_reservation.view');
        Route::patch('/{id}/deactivate', DesactivateFixedReservationController::class)->name('fixed_reservation.deactivate');
        Route::patch('/{id}/desactivate', DesactivateFixedReservationController::class)->name('fixed_reservation.deactivate.legacy');
    });

    Route::prefix('fixed-reservation-conflicts')->middleware('permission')->group(function () {
        Route::patch('/{id}/resolve', ResolveFixedReservationConflictController::class)->name('fixed_reservation_conflict.resolve');
    });


    // Rutas de Reservas por Sucursal (Lectura)
    Route::prefix('branches/{branch_id}/reservations')->middleware('permission')->group(function () {
        Route::get('', GetBranchReservationsController::class)->name('reservation.branch.collection');
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

        Route::get('{id}', ShowReservationController::class)->name('reservation.view');
        Route::patch('{id}/cancel', CancelReservationController::class)->name('reservation.cancel');
        Route::patch('{id}/confirm', ConfirmReservationController::class)->name('reservation.confirm');


        Route::post('{id}/payments', RegisterManualPaymentController::class)->name('payment.create');
        Route::get('{id}/payments', GetReservationPaymentsController::class)->name('payment.view');


        Route::post('{id}/refunds', CreateRefundController::class)->name('reservation.refund.create');
    });


    Route::prefix('refunds')->middleware('permission')->group(function () {
        Route::get('/{id}', GetRefundController::class)->name('refund.view');
        Route::patch('/{id}/complete', CompleteRefundController::class)->name('refund.complete');
    });
    Route::prefix('branches/{branch_id}/refunds')->middleware('permission')->group(function () {
        Route::get('', ListRefundsController::class)->name('refund.collection');
    });
});
