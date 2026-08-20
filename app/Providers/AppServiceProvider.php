<?php

namespace App\Providers;

use App\Application\Auth\Contracts\PasswordHasher;
use App\Application\Auth\Contracts\TokenGenerator;
use App\Domain\Users\Repositories\UserRepository;
use App\Infrastructure\Auth\LaravelPasswordHasher;
use App\Infrastructure\Auth\Sanctum\SanctumTokenGenerator;
use App\Infrastructure\Persistence\EloquentUserRepository;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(UserRepository::class, EloquentUserRepository::class);
        $this->app->bind(TokenGenerator::class, SanctumTokenGenerator::class);
        $this->app->bind(PasswordHasher::class, LaravelPasswordHasher::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
