<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use WorkDoneRight\ApiGuardian\Http\Controllers\Api\AnalyticsController;
use WorkDoneRight\ApiGuardian\Http\Controllers\Api\CircuitBreakerController;
use WorkDoneRight\ApiGuardian\Http\Controllers\Api\ErrorController;
use WorkDoneRight\ApiGuardian\Http\Controllers\Api\HealthController;

/*
|--------------------------------------------------------------------------
| API Guardian API Routes
|--------------------------------------------------------------------------
|
| These routes provide a REST API for error management and monitoring.
| They are shared by all UI frameworks (Livewire, Vue, React).
|
| Route configuration (prefix, middleware, namespace) is handled
| by the ApiGuardianServiceProvider based on package configuration.
|
*/

Route::name('api-guardian.api.')->group(function (): void {

    // Health Check (no auth required)
    Route::get('/health', [HealthController::class, 'index'])->name('health');

    // Error Management
    Route::prefix('errors')->name('errors.')->group(function (): void {
        Route::get('/', [ErrorController::class, 'index'])->name('index');
        Route::get('/stats', [ErrorController::class, 'stats'])->name('stats');
        Route::get('/{id}', [ErrorController::class, 'show'])->name('show');
        Route::post('/{id}/resolve', [ErrorController::class, 'resolve'])->name('resolve');
        Route::delete('/{id}', [ErrorController::class, 'destroy'])->name('destroy');
        Route::post('/bulk-resolve', [ErrorController::class, 'bulkResolve'])->name('bulk-resolve');
        Route::delete('/bulk-destroy', [ErrorController::class, 'bulkDestroy'])->name('bulk-destroy');
    });

    // Analytics
    Route::prefix('analytics')->name('analytics.')->group(function (): void {
        Route::get('/', [AnalyticsController::class, 'index'])->name('index');
        Route::get('/trends', [AnalyticsController::class, 'trends'])->name('trends');
        Route::get('/top-errors', [AnalyticsController::class, 'topErrors'])->name('top-errors');
        Route::get('/distribution', [AnalyticsController::class, 'distribution'])->name('distribution');
        Route::get('/error-rate', [AnalyticsController::class, 'errorRate'])->name('error-rate');
        Route::get('/export', [AnalyticsController::class, 'export'])->name('export');
    });

    // Circuit Breakers
    Route::prefix('circuit-breakers')->name('circuit-breakers.')->group(function (): void {
        Route::get('/', [CircuitBreakerController::class, 'index'])->name('index');
        Route::get('/stats', [CircuitBreakerController::class, 'stats'])->name('stats');
        Route::get('/{id}', [CircuitBreakerController::class, 'show'])->name('show');
        Route::post('/{id}/reset', [CircuitBreakerController::class, 'reset'])->name('reset');
        Route::post('/{id}/test', [CircuitBreakerController::class, 'test'])->name('test');
        Route::get('/{id}/history', [CircuitBreakerController::class, 'history'])->name('history');
    });
});
