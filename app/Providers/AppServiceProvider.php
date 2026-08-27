<?php

namespace App\Providers;

use App\Application\Auth\Contracts\PasswordHasher;
use App\Application\Auth\Contracts\TokenGenerator;
use App\Domain\Branches\Repositories\BranchRepository;
use App\Domain\Clubs\Repositories\ClubRepository;
use App\Domain\Courts\Repositories\CourtRepository;
use App\Domain\Courts\Repositories\TipoCourtRepository;
use App\Domain\Memberships\Repositories\MembershipRepository;
use App\Domain\Permissions\Repositories\PermissionRepository;
use App\Domain\Pricing\Repositories\CourtPriceRepository;
use App\Domain\Roles\Repositories\RoleRepository;
use App\Domain\Users\Repositories\UserRepository;
use App\Infrastructure\Auth\LaravelPasswordHasher;
use App\Infrastructure\Auth\Sanctum\SanctumTokenGenerator;
use App\Infrastructure\Persistence\EloquentBranchRepository;
use App\Infrastructure\Persistence\EloquentClubRepository;
use App\Infrastructure\Persistence\EloquentCourtPriceRepository;
use App\Infrastructure\Persistence\EloquentCourtRepository;
use App\Infrastructure\Persistence\EloquentMembershipRepository;
use App\Infrastructure\Persistence\EloquentPermissionRepository;
use App\Infrastructure\Persistence\EloquentRoleRepository;
use App\Infrastructure\Persistence\EloquentTipoCourtRepository;
use App\Infrastructure\Persistence\EloquentUserRepository;
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
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
