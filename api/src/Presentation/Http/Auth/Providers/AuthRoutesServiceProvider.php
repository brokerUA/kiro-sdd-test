<?php

declare(strict_types=1);

namespace Presentation\Http\Auth\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AuthRoutesServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Route::prefix('api/auth')->group(__DIR__ . '/../routes.php');
    }
}
