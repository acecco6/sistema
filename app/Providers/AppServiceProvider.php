<?php

namespace App\Providers;

use App\Application\Auth\Contracts\{PasswordHasher, TokenGenerator};
use App\Application\Notifications\Listeners\SendRefundCompletedNotification;
use App\Application\Notifications\Listeners\SendReservationCancelledNotification;
use App\Application\Notifications\Listeners\SendReservationConfirmedNotification;
use App\Application\Notifications\Listeners\SendReservationExpiredNotification;
use App\Application\Payments\Gateways\PaymentGateway;
use App\Application\Payments\Webhooks\WebhookSignatureValidator;
use App\Domain\Branches\Repositories\BranchRepository;
use App\Domain\Clubs\Repositories\ClubRepository;
use App\Domain\Courts\Repositories\{CourtRepository, TipoCourtRepository};
use App\Domain\Courts\Repositories\IntervalTimeTipoCourtRepository;
use App\Domain\Memberships\Repositories\MembershipRepository;
use App\Domain\Notifications\Repositories\EmailLogRepository;
use App\Domain\Payments\Events\RefundCompleted;
use App\Domain\Payments\Repositories\PaymentRefundRepository;
use App\Domain\Payments\Repositories\PaymentRepository;
use App\Domain\Permissions\Repositories\PermissionRepository;
use App\Domain\Pricing\Repositories\CourtPriceRepository;
use App\Domain\Reservations\Events\ReservationCancelled;
use App\Domain\Reservations\Events\ReservationConfirmed;
use App\Domain\Reservations\Events\ReservationExpired;
use App\Domain\Reservations\Repositories\ReservationRepository;
use App\Domain\Roles\Repositories\RoleRepository;
use App\Domain\Users\Repositories\UserRepository;
use App\Infrastructure\Auth\LaravelPasswordHasher;
use App\Infrastructure\Auth\Sanctum\SanctumTokenGenerator;
use App\Infrastructure\Payments\Gateways\MercadoPagoPaymentGateway;
use App\Infrastructure\Payments\Webhooks\MercadoPagoWebhookSignatureValidator;
use App\Infrastructure\Persistence\{EloquentBranchRepository, EloquentClubRepository, EloquentCourtPriceRepository, EloquentCourtRepository, EloquentMembershipRepository, EloquentPermissionRepository, EloquentReservationRepository, EloquentRoleRepository, EloquentTipoCourtRepository, EloquentUserRepository};
use App\Infrastructure\Persistence\EloquentEmailLogRepository;
use App\Infrastructure\Persistence\EloquentIntervalTimeTipoCourtRepository;
use App\Infrastructure\Persistence\EloquentPaymentRefundRepository;
use App\Infrastructure\Persistence\EloquentPaymentRepository;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;


class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Cuando pones $this->app->bind(UserRepository::class, EloquentUserRepository::class);, 
        // le estás diciendo a Laravel: "Cada vez que una parte de mi aplicación pida la interfaz UserRepository, 
        // dale automáticamente una instancia de la clase EloquentUserRepository".
        $this->app->bind(UserRepository::class, EloquentUserRepository::class);
        $this->app->bind(TokenGenerator::class, SanctumTokenGenerator::class);
        $this->app->bind(PasswordHasher::class, LaravelPasswordHasher::class);
        $this->app->bind(ClubRepository::class, EloquentClubRepository::class);
        $this->app->bind(BranchRepository::class, EloquentBranchRepository::class);
        $this->app->bind(RoleRepository::class, EloquentRoleRepository::class);
        $this->app->bind(MembershipRepository::class, EloquentMembershipRepository::class);
        $this->app->bind(PermissionRepository::class, EloquentPermissionRepository::class);
        $this->app->bind(CourtRepository::class, EloquentCourtRepository::class);
        $this->app->bind(TipoCourtRepository::class, EloquentTipoCourtRepository::class);
        $this->app->bind(CourtPriceRepository::class, EloquentCourtPriceRepository::class);
        $this->app->bind(ReservationRepository::class, EloquentReservationRepository::class);
        $this->app->bind(IntervalTimeTipoCourtRepository::class, EloquentIntervalTimeTipoCourtRepository::class);
        $this->app->bind(PaymentRepository::class, EloquentPaymentRepository::class);
        $this->app->bind(PaymentGateway::class, MercadoPagoPaymentGateway::class);
        $this->app->bind(WebhookSignatureValidator::class, MercadoPagoWebhookSignatureValidator::class);
        $this->app->bind(PaymentRefundRepository::class, EloquentPaymentRefundRepository::class,);
        $this->app->bind(EmailLogRepository::class, EloquentEmailLogRepository::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Event::listen(ReservationConfirmed::class, SendReservationConfirmedNotification::class);
        Event::listen(ReservationCancelled::class, SendReservationCancelledNotification::class,);
        Event::listen(ReservationExpired::class, SendReservationExpiredNotification::class,);
        Event::listen(RefundCompleted::class, SendRefundCompletedNotification::class,);


        Route::pattern('id', '[0-9]+');
        Route::pattern('court_id', '[0-9]+');
        Route::pattern('branch_id', '[0-9]+');
        Route::pattern('club_id', '[0-9]+');
        Route::pattern('membership_id', '[0-9]+');
        Route::pattern('court_price_id', '[0-9]+');
        Route::pattern('promotion_id', '[0-9]+');
    }
}
