<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use WorkDoneRight\ApiGuardian\Livewire\Analytics;
use WorkDoneRight\ApiGuardian\Livewire\CircuitBreakers;
use WorkDoneRight\ApiGuardian\Livewire\Dashboard;
use WorkDoneRight\ApiGuardian\Livewire\ErrorDetail;
use WorkDoneRight\ApiGuardian\Livewire\ErrorFeed;

/*
|--------------------------------------------------------------------------
| API Guardian Livewire Routes
|--------------------------------------------------------------------------
|
| These routes provide the Livewire-based dashboard interface.
| Route configuration (prefix, middleware, namespace) is handled
| by the ApiGuardianServiceProvider.
|
*/

Route::name('api-guardian.livewire.')->group(function (): void {

    // Dashboard
    Route::get('/', Dashboard::class)->name('dashboard');

    // Error Management
    Route::get('/errors', ErrorFeed::class)->name('errors');

    Route::get('/errors/{id}', ErrorDetail::class)->name('error.show');

    // Analytics
    Route::get('/analytics', Analytics::class)->name('analytics');

    // Circuit Breakers
    Route::get('/circuit-breakers', CircuitBreakers::class)->name('circuit-breakers');
});
