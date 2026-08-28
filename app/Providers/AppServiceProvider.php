<?php

namespace App\Providers;

use Illuminate\Support\Facades\Route;
use App\Application\Auth\Contracts\{
    PasswordHasher,
    TokenGenerator
};
use App\Domain\Branches\Repositories\BranchRepository;
use App\Domain\Clubs\Repositories\ClubRepository;
use App\Domain\Courts\Repositories\{
    CourtRepository,
    TipoCourtRepository
};
use App\Domain\Courts\Repositories\IntervalTimeTipoCourtRepository;
use App\Domain\Memberships\Repositories\MembershipRepository;
use App\Domain\Permissions\Repositories\PermissionRepository;
use App\Domain\Pricing\Repositories\CourtPriceRepository;
use App\Domain\Reservations\Repositories\ReservationRepository;
use App\Domain\Roles\Repositories\RoleRepository;
use App\Domain\Users\Repositories\UserRepository;
use App\Infrastructure\Auth\LaravelPasswordHasher;
use App\Infrastructure\Auth\Sanctum\SanctumTokenGenerator;
use App\Infrastructure\Persistence\{
    EloquentBranchRepository,
    EloquentClubRepository,
    EloquentCourtPriceRepository,
    EloquentCourtRepository,
    EloquentMembershipRepository,
    EloquentPermissionRepository,
    EloquentReservationRepository,
    EloquentRoleRepository,
    EloquentTipoCourtRepository,
    EloquentUserRepository
};
use App\Infrastructure\Persistence\EloquentIntervalTimeTipoCourtRepository;
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
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Route::pattern('id', '[0-9]+');
        Route::pattern('court_id', '[0-9]+');
        Route::pattern('branch_id', '[0-9]+');
        Route::pattern('club_id', '[0-9]+');
        Route::pattern('membership_id', '[0-9]+');
        Route::pattern('court_price_id', '[0-9]+');
        Route::pattern('promotion_id', '[0-9]+');
    }
}
