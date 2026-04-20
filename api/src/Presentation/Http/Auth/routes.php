<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Presentation\Http\Auth\Controllers\CompletePasswordResetController;
use Presentation\Http\Auth\Controllers\LoginController;
use Presentation\Http\Auth\Controllers\LogoutController;
use Presentation\Http\Auth\Controllers\RequestPasswordResetController;

Route::post('/login',                   LoginController::class);
Route::post('/logout',                  LogoutController::class);
Route::post('/password-reset/request',  RequestPasswordResetController::class);
Route::post('/password-reset/complete', CompletePasswordResetController::class);
