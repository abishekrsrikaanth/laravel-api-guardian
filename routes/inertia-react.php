<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use WorkDoneRight\ApiGuardian\Http\Controllers\Inertia\AnalyticsController;
use WorkDoneRight\ApiGuardian\Http\Controllers\Inertia\CircuitBreakerController;
use WorkDoneRight\ApiGuardian\Http\Controllers\Inertia\DashboardController;
use WorkDoneRight\ApiGuardian\Http\Controllers\Inertia\ErrorController;

/*
|--------------------------------------------------------------------------
| API Guardian Inertia React Routes
|--------------------------------------------------------------------------
|
| These routes provide the Inertia + React dashboard interface.
| Route configuration (prefix, middleware, namespace) is handled
| by the ApiGuardianServiceProvider.
|
*/

Route::name('api-guardian.react.')->group(function (): void {

    // Dashboard
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    // Error Management
    Route::get('/errors', [ErrorController::class, 'index'])->name('errors.index');
    Route::get('/errors/{id}', [ErrorController::class, 'show'])->name('errors.show');

    // Analytics
    Route::get('/analytics', [AnalyticsController::class, 'index'])->name('analytics');

    // Circuit Breakers
    Route::get('/circuit-breakers', [CircuitBreakerController::class, 'index'])->name('circuit-breakers');
});
