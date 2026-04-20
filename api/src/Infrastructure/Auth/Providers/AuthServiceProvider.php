<?php

declare(strict_types=1);

namespace Infrastructure\Auth\Providers;

use Domain\Auth\Repositories\ResetTokenRepositoryInterface;
use Domain\Auth\Repositories\SessionRepositoryInterface;
use Domain\Auth\Repositories\UserRepositoryInterface;
use Infrastructure\Auth\Repositories\EloquentResetTokenRepository;
use Infrastructure\Auth\Repositories\EloquentSessionRepository;
use Infrastructure\Auth\Repositories\EloquentUserRepository;
use Illuminate\Support\ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(UserRepositoryInterface::class, EloquentUserRepository::class);
        $this->app->bind(SessionRepositoryInterface::class, EloquentSessionRepository::class);
        $this->app->bind(ResetTokenRepositoryInterface::class, EloquentResetTokenRepository::class);
    }
}
